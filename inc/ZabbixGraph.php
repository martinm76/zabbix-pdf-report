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
 * Inspect history values for one or more items over a time range.
 * Returns ['min' => float|null, 'max' => float|null, 'hasData' => bool].
 */
private function getValueRange(array $itemIds, int $startTime, int $endTime): array
{
    if (empty($itemIds)) {
        return ['min' => null, 'max' => null, 'hasData' => false];
    }

    // Fetch each item's value_type (needed for history.get)
    $items = ZabbixAPI::fetch_array('item', 'get', [
        'itemids' => $itemIds,
        'output'  => ['itemid', 'value_type'],
    ]);

    $globalMin = null;
    $globalMax = null;
    $hasData   = false;

    if (!is_array($items)) {
        return ['min' => null, 'max' => null, 'hasData' => false];
    }

    foreach ($items as $item) {
        $valueType = (int)$item['value_type'];
        // Only numeric types: 0=float, 3=unsigned
        if ($valueType !== 0 && $valueType !== 3) {
            continue;
        }

        $history = ZabbixAPI::fetch_array('history', 'get', [
            'itemids'   => [$item['itemid']],
            'history'   => $valueType,
            'time_from' => $startTime,
            'time_till' => $endTime,
            'output'    => ['value'],
        ]);

        if (!is_array($history) || empty($history)) {
            continue;
        }

        $hasData = true;
        foreach ($history as $row) {
            $v = (float)$row['value'];
            if ($globalMin === null || $v < $globalMin) $globalMin = $v;
            if ($globalMax === null || $v > $globalMax) $globalMax = $v;
        }
    }

    return ['min' => $globalMin, 'max' => $globalMax, 'hasData' => $hasData];
}

/**
 * Decide whether forced Y-axis bounds are needed to avoid the
 * "Y axis MAX value must be greater than Y axis MIN value" error.
 * Returns chart URL parameters to merge in (or empty array).
 */
private function forcedYBoundsIfNeeded(array $range): array
{
    // If we have data and there's actual variation, no override needed
    if ($range['hasData'] && $range['min'] !== $range['max']) {
        return [];
    }

    if (!$range['hasData'] || $range['max'] === null) {
        // No data at all — force 0 to 1
        $yMin = 0;
        $yMax = 1;
    } else {
        // All values identical
        $value = $range['max'];
        $yMin  = ($value < 0) ? $value - 1 : 0;
        $yMax  = $value + 1;
    }

    return [
        'ymin_type' => 1,    // 1 = fixed
        'yaxismin'  => $yMin,
        'ymax_type' => 1,
        'yaxismax'  => $yMax,
    ];
}

/**
 * Render a regular graph (chart2.php) to disk.
 */
public function saveGraph(int $graphId, int $startTime, int $endTime, int $width, int $height, string $outFile): bool
{
    // Look up items belonging to this graph
    $graphs = ZabbixAPI::fetch_array('graph', 'get', [
        'graphids'    => [$graphId],
        'selectItems' => ['itemid'],
        'output'      => ['graphid'],
    ]);

    $itemIds = [];
    if (is_array($graphs) && !empty($graphs[0]['items'])) {
        foreach ($graphs[0]['items'] as $i) {
            $itemIds[] = $i['itemid'];
        }
    }

    $range = $this->getValueRange($itemIds, $startTime, $endTime);
    $extraParams = $this->forcedYBoundsIfNeeded($range);

    $params = [
        'graphid'    => $graphId,
        'profileIdx' => 'web.charts.filter',
        'from'       => date('Y-m-d H:i:s', $startTime),
        'to'         => date('Y-m-d H:i:s', $endTime),
        'width'      => $width,
        'height'     => $height,
    ];
    $params = array_merge($params, $extraParams);

    $url = $this->serverUrl . 'chart2.php?' . http_build_query($params);
    return $this->fetchToFile($url, $outFile);
}

/**
 * Render an item history graph (chart.php) to disk.
 */
public function saveItemGraph(int $itemId, int $startTime, int $endTime, int $width, int $height, string $outFile): bool
{
    $range = $this->getValueRange([$itemId], $startTime, $endTime);
    $extraParams = $this->forcedYBoundsIfNeeded($range);

    $params = [
        'itemids'    => [$itemId],
        'profileIdx' => 'web.item.graph.filter',
        'from'       => date('Y-m-d H:i:s', $startTime),
        'to'         => date('Y-m-d H:i:s', $endTime),
        'width'      => $width,
        'height'     => $height,
    ];
    $params = array_merge($params, $extraParams);

    $url = $this->serverUrl . 'chart.php?' . http_build_query($params);
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
