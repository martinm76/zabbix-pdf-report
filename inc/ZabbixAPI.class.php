<?php
/**
 * zabbix-pdf-report — 2.x
 *
 * Zabbix JSON-RPC client, rewritten for Zabbix 7.0+ and PHP 8.0+.
 *
 * Major changes vs. 1.x:
 *   - Authenticates with HTTP `Authorization: Bearer <token>` header
 *     (the legacy `"auth"` JSON-RPC field is removed in Zabbix 7.2 and
 *      deprecated in 7.0, so 2.x never emits it).
 *   - Supports pre-created API tokens (recommended) in addition to
 *     username/password login.
 *   - Refuses to run against Zabbix < 7.0 with a clear error pointing
 *     users at the legacy-1.x branch.
 *   - PHP 8 clean: strict types, typed properties, no deprecated calls.
 *   - Throws ZabbixApiException on transport/RPC errors; the legacy
 *     getLastError() accessor is retained for callers that prefer
 *     boolean-return semantics.
 *
 * The static facade (ZabbixAPI::login / ::fetch_array / ::getLastError /
 * ::debugEnabled / ::logout) is preserved so existing callers in
 * chooser.php and createpdf.php keep working with minimal edits.
 *
 * @package   zabbix-pdf-report
 * @license   GPL-3.0-or-later
 * @link      https://github.com/martinm76/zabbix-pdf-report
 */
declare(strict_types=1);

require_once __DIR__ . '/ZabbixApiException.php';

final class ZabbixAPI
{
    public const PHPAPI_VERSION       = '2.0.0';
    public const ZABBIX_API_ENDPOINT  = 'api_jsonrpc.php';
    public const MIN_ZABBIX_VERSION   = '7.0';

    /** Singleton instance. */
    private static ?ZabbixAPI $instance = null;

    private string  $url        = '';
    private string  $username   = '';
    private string  $password   = '';
    private ?string $authToken  = null;       // Bearer token (session or API token)
    private bool    $tokenIsApiKey = false;   // true ⇒ never call user.logout()
    private ?string $apiVersion = null;       // cached, e.g. "7.0.12"
    private bool    $debug      = false;
    private bool    $verifyTls  = false;      // preserved 1.x default
    private mixed   $lastError  = false;      // legacy getLastError() value
    private int     $requestId  = 0;
    /** @var resource|\CurlHandle|null */
    private $curl = null;

    // ------------------------------------------------------------------
    // Construction (singleton; no public new/clone)
    // ------------------------------------------------------------------

    private function __construct() {}
    private function __clone() {}
    public function __wakeup(): void { throw new RuntimeException('Cannot unserialize ZabbixAPI'); }

    private static function instance(): self
    {
        return self::$instance ??= new self();
    }

    // ------------------------------------------------------------------
    // Public static facade — preserved from 1.x
    // ------------------------------------------------------------------

    /**
     * Authenticate against Zabbix. Two modes:
     *  - If $username is empty and $password looks like an API token,
     *    we use it directly as a Bearer token (no user.login call).
     *  - Otherwise we POST user.login and cache the returned token.
     *
     * Returns true on success, false on failure (lastError is populated).
     */
    public static function login(string $url, string $username, string $password): bool
    {
        $self = self::instance();
        $self->url       = self::normalizeUrl($url);
        $self->username  = $username;
        $self->password  = $password;
        $self->lastError = false;

        try {
            // Verify the server is reachable and meets our minimum version.
            $self->ensureVersionSupported();

            // Mode 1: pre-created API token passed in via $password (and no username)
            if ($username === '' && $password !== '') {
                $self->authToken     = $password;
                $self->tokenIsApiKey = true;
                // Validate it with a cheap authenticated call.
                $self->call('user.checkAuthentication', ['token' => $password]);
                return true;
            }

            // Mode 2: classic username/password login
            $token = $self->call('user.login', [
                'username' => $username,    // Zabbix ≥ 6.4 field name
                'password' => $password,
            ]);

            if (!is_string($token) || $token === '') {
                $self->lastError = 'Empty token returned from user.login';
                return false;
            }

            $self->authToken     = $token;
            $self->tokenIsApiKey = false;
            return true;
        } catch (ZabbixApiException $e) {
            $self->lastError = $e->getRpcError() ?? $e->getMessage();
            $self->authToken = null;
            return false;
        }
    }

    /**
     * Logout. Signature kept for backward compatibility, but $url/$username
     * are now ignored — we use the cached singleton state.
     */
    public static function logout(string $url = '', string $username = '', string $password = ''): bool
    {
        $self = self::instance();
        if ($self->authToken === null) {
            return true;
        }
        // Never invalidate a long-lived API token.
        if ($self->tokenIsApiKey) {
            $self->authToken = null;
            return true;
        }
        try {
            $self->call('user.logout', []);
            $self->authToken = null;
            return true;
        } catch (ZabbixApiException $e) {
            $self->lastError = $e->getRpcError() ?? $e->getMessage();
            return false;
        }
    }

    public static function debugEnabled(bool $value): void
    {
        self::instance()->debug = $value;
    }

    /** Toggle TLS peer/host verification. Default: false (legacy behaviour). */
    public static function verifyTls(bool $value): void
    {
        self::instance()->verifyTls = $value;
    }

    /**
     * Generic call. Returns the decoded `result` field on success.
     * Returns false on failure (use getLastError() to inspect).
     *
     * @param array<string,mixed> $properties
     * @return mixed
     */
    public static function fetch(string $object, string $method, array $properties = []): mixed
    {
        $self = self::instance();
        $self->lastError = false;
        try {
            return $self->call($object . '.' . $method, $properties);
        } catch (ZabbixApiException $e) {
            $self->lastError = $e->getRpcError() ?? $e->getMessage();
            return false;
        }
    }

    /** @param array<string,mixed> $properties */
    public static function query(string $object, string $method, array $properties = []): bool
    {
        return self::fetch($object, $method, $properties) !== false;
    }

    /**
     * @param array<string,mixed> $properties
     * @return array<int|string,mixed>
     */
    public static function fetch_array(string $object, string $method, array $properties = []): array
    {
        $r = self::fetch($object, $method, $properties);
        if ($r === false) {
            return [];
        }
        return is_array($r) ? $r : [$r];
    }

    /** @param array<string,mixed> $properties */
    public static function fetch_string(string $object, string $method, array $properties = []): string
    {
        $r = self::fetch($object, $method, $properties);
        if (is_array($r)) {
            return self::firstScalar($r) ?? '';
        }
        return $r === false ? '' : (string) $r;
    }

    /**
     * @param array<string,mixed> $properties
     * @return mixed First row of the result array, or the result itself if scalar.
     */
    public static function fetch_row(string $object, string $method, array $properties = []): mixed
    {
        $r = self::fetch($object, $method, $properties);
        if (is_array($r)) {
            foreach ($r as $row) {
                return $row;
            }
            return null;
        }
        return $r;
    }

    /**
     * @param array<string,mixed> $properties
     * @return array<int,mixed>
     */
    public static function fetch_column(string $object, string $method, array $properties = []): array
    {
        $r = self::fetch($object, $method, $properties);
        if (!is_array($r)) {
            return $r === false ? [] : [$r];
        }
        $out = [];
        foreach ($r as $row) {
            if (is_array($row)) {
                $out[] = array_shift($row);
            } else {
                $out[] = $row;
            }
        }
        return $out;
    }

    /** @return mixed The last error: array (RPC error), string, or false if no error. */
    public static function getLastError(): mixed
    {
        return self::instance()->lastError;
    }

    /** Returns the cached Zabbix version, e.g. "7.0.12". */
    public static function getApiVersion(): string
    {
        $self = self::instance();
        if ($self->apiVersion === null) {
            $self->apiVersion = (string) $self->call('apiinfo.version', [], unauthenticated: true);
        }
        return $self->apiVersion;
    }

    // ------------------------------------------------------------------
    // Frontend cookie helper for chart2.php / chart.php
    // ------------------------------------------------------------------

    /**
     * Performs a frontend (HTML) login and returns the value of the
     * `zbx_session` cookie. Required because chart2.php does NOT accept
     * Bearer tokens — only the frontend session cookie.
     *
     * Pass the returned string to cURL as the `zbx_session` cookie value
     * when fetching graph PNGs.
     *
     * @throws ZabbixApiException on failure.
     */
    public static function getFrontendCookie(string $username, string $password): string
    {
        $self = self::instance();
        if ($self->url === '') {
            throw new ZabbixApiException('ZabbixAPI::login() must be called first to set the server URL');
        }

        $loginUrl = $self->url . 'index.php';
        $ch = curl_init($loginUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYHOST => $self->verifyTls ? 2 : 0,
            CURLOPT_SSL_VERIFYPEER => $self->verifyTls,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'name'      => $username,
                'password'  => $password,
                'autologin' => 1,
                'enter'     => 'Sign in',
            ]),
            CURLOPT_USERAGENT      => 'zabbix-pdf-report/' . self::PHPAPI_VERSION,
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new ZabbixApiException('Frontend login transport error: ' . $err);
        }
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $headers = substr((string) $response, 0, $headerSize);
        // Zabbix sets `zbx_session=...` (5.x+); older releases used `zbx_sessionid`.
        if (preg_match('/^Set-Cookie:\s*zbx_session(?:id)?=([^;\s]+)/mi', $headers, $m)) {
            return $m[1];
        }
        throw new ZabbixApiException('Frontend login failed: zbx_session cookie not found in response');
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    /**
     * Perform a JSON-RPC call. Returns the `result` value.
     *
     * @param array<string,mixed> $params
     * @throws ZabbixApiException
     */
    private function call(string $method, array $params = [], bool $unauthenticated = false): mixed
    {
        $payload = [
            'jsonrpc' => '2.0',
            'method'  => $method,
            'params'  => (object) $params,   // empty params must serialise as {} not []
            'id'      => ++$this->requestId,
        ];
        if (count($params) > 0) {
            $payload['params'] = $params;
        }

        $headers = ['Content-Type: application/json-rpc'];
        if (!$unauthenticated && $this->authToken !== null && $method !== 'user.login') {
            $headers[] = 'Authorization: Bearer ' . $this->authToken;
        }

        $raw = $this->httpPost($this->url . self::ZABBIX_API_ENDPOINT, json_encode($payload, JSON_THROW_ON_ERROR), $headers);

        if ($this->debug) {
            error_log('[ZabbixAPI] ← ' . $raw);
        }

        try {
            /** @var array<string,mixed> $decoded */
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ZabbixApiException('Malformed JSON from Zabbix: ' . $e->getMessage(), null, 0, $e);
        }

        if (isset($decoded['error'])) {
            $err = $decoded['error'];
            $msg = sprintf(
                'Zabbix API error (%s): %s — %s',
                (string) ($err['code'] ?? '?'),
                (string) ($err['message'] ?? 'unknown'),
                (string) ($err['data'] ?? '')
            );
            throw new ZabbixApiException($msg, $err);
        }
        if (!array_key_exists('result', $decoded)) {
            throw new ZabbixApiException('Zabbix response missing both "result" and "error" fields');
        }
        return $decoded['result'];
    }

    /**
     * @param array<int,string> $headers
     */
    private function httpPost(string $url, string $body, array $headers): string
    {
        $ch = $this->curl ??= curl_init();
        curl_reset($ch);
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_ENCODING       => 'gzip',
            CURLOPT_SSL_VERIFYHOST => $this->verifyTls ? 2 : 0,
            CURLOPT_SSL_VERIFYPEER => $this->verifyTls,
            CURLOPT_USERAGENT      => 'zabbix-pdf-report/' . self::PHPAPI_VERSION,
        ]);

        if ($this->debug) {
            error_log('[ZabbixAPI] → ' . $url . ' ' . $body);
        }

        $resp = curl_exec($ch);
        if ($resp === false) {
            throw new ZabbixApiException('cURL error: ' . curl_error($ch));
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($status < 200 || $status >= 300) {
            throw new ZabbixApiException(sprintf('HTTP %d from %s', $status, $url));
        }
        return (string) $resp;
    }

    private function ensureVersionSupported(): void
    {
        $version = $this->apiVersion ??= (string) $this->call('apiinfo.version', [], unauthenticated: true);
        if (version_compare($version, self::MIN_ZABBIX_VERSION, '<')) {
            throw new ZabbixApiException(sprintf(
                'zabbix-pdf-report 2.x requires Zabbix %s or newer, got %s. '
                . 'For older Zabbix versions please use the legacy-1.x branch: '
                . 'https://github.com/martinm76/zabbix-pdf-report/tree/legacy-1.x',
                self::MIN_ZABBIX_VERSION,
                $version
            ));
        }
    }

    private static function normalizeUrl(string $url): string
    {
        return str_ends_with($url, '/') ? $url : $url . '/';
    }

    /** @param array<int|string,mixed> $a */
    private static function firstScalar(array $a): ?string
    {
        foreach ($a as $v) {
            if (is_array($v)) {
                $r = self::firstScalar($v);
                if ($r !== null) {
                    return $r;
                }
            } else {
                return (string) $v;
            }
        }
        return null;
    }
}
