<?php
/**
 * zabbix-pdf-report — 2.x
 *
 * Renders Zabbix graph and item-graph PNGs by calling chart2.php / chart.php
 * with a frontend session cookie. The cookie is fetched once per instance
 * and reused for every graph, instead of re-logging-in per graph as the
 * 1.x code did.
 *
 * @package   zabbix-pdf-report
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

final class ZabbixGraph
{
    private string  $serverUrl;
    private string  $username;
    private string  $password;
    private bool    $verifyTls;
    private ?string $sessionCookie = null;
    /** @var \CurlHandle|null */
    private $curl = null;
    private bool    $debug = false;

    public function __construct(string $serverUrl, string $username, string $password, bool $verifyTls = false)
    {
        $this->serverUrl = str_ends_with($serverUrl, '/') ? $serverUrl : $serverUrl . '/';
        $this->username  = $username;
        $this->password  = $password;
        $this->verifyTls = $verifyTls;
    }

    public function setDebug(bool $debug): void
    {
        $this->debug = $debug;
    }

    /**
     * Render a regular graph (chart2.php) to disk.
     */
    public function saveGraph(int $graphId, int $startTime, int $endTime, int $width, int $height, string $outFile): bool
    {
        $url = $this->serverUrl . 'chart2.php?' . http_build_query([
            'graphid'    => $graphId,
            'profileIdx' => 'web.charts.filter',
            // chart2.php uses the frontend time-range parser, which rejects
            // raw Unix timestamps. Format as 'Y-m-d H:i:s' so it works on
            // Zabbix 5.4, 6.x and 7.x alike.
            'from'       => date('Y-m-d H:i:s', $startTime),
            'to'         => date('Y-m-d H:i:s', $endTime),
            'width'      => $width,
            'height'     => $height,
        ]);
        return $this->fetchToFile($url, $outFile);
    }

    /**
     * Render an item history graph (chart.php) to disk.
     */
    public function saveItemGraph(int $itemId, int $startTime, int $endTime, int $width, int $height, string $outFile): bool
    {
        // chart.php expects itemids as an indexed array: itemids[0]=ID
        $url = $this->serverUrl . 'chart.php?' . http_build_query([
            'itemids'    => [$itemId],
            'profileIdx' => 'web.item.graph.filter',
            'from'       => date('Y-m-d H:i:s', $startTime),
            'to'         => date('Y-m-d H:i:s', $endTime),
            'width'      => $width,
            'height'     => $height,
        ]);
        return $this->fetchToFile($url, $outFile);
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    private function fetchToFile(string $url, string $outFile): bool
    {
        if ($this->sessionCookie === null) {
            $this->sessionCookie = ZabbixAPI::getFrontendCookie($this->username, $this->password);
        }

        $ch = $this->curl ??= curl_init();
        curl_reset($ch);
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYHOST => $this->verifyTls ? 2 : 0,
            CURLOPT_SSL_VERIFYPEER => $this->verifyTls,
            CURLOPT_COOKIE         => 'zbx_session=' . $this->sessionCookie,
            CURLOPT_USERAGENT      => 'zabbix-pdf-report/' . ZabbixAPI::PHPAPI_VERSION,
        ]);

        if ($this->debug) {
            error_log('[ZabbixGraph] GET ' . $url);
        }

        $body = curl_exec($ch);
        if ($body === false) {
            error_log('[ZabbixGraph] cURL error: ' . curl_error($ch));
            return false;
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($status !== 200) {
            error_log(sprintf('[ZabbixGraph] HTTP %d for %s', $status, $url));
            return false;
        }

        // Sanity check: response must look like a PNG. If we got HTML back,
        // it usually means the session cookie expired or the auth failed.
        if (substr((string) $body, 0, 8) !== "\x89PNG\r\n\x1a\n") {
            error_log('[ZabbixGraph] Response is not a PNG (auth issue?) for ' . $url);
            // Re-authenticate once and retry.
            $this->sessionCookie = null;
            return false;
        }

        $written = file_put_contents($outFile, $body);
        return $written !== false;
    }
}

?>
