<?php
/**
 * zabbix-pdf-report — 2.x
 *
 * Report-content builder. Writes the intermediate text format consumed
 * by createpdf.php's rendering loop.
 *
 * Public surface preserved from 1.x:
 *   CreatePDF(array $hostarray): void
 *   tempdir(?string $dir, string $prefix = 'zabbix_report_'): string
 *
 * @package   zabbix-pdf-report
 * @license   GPL-3.0-or-later
 */
declare(strict_types=1);

require_once __DIR__ . '/ZabbixGraph.php';

// ---------------------------------------------------------------------
// Small utilities
// ---------------------------------------------------------------------

/**
 * Place a PNG logo onto a Cezpdf page with auto-scaling and format normalisation.
 *
 * Options (all optional):
 *   maxW      float   max width in PDF points          (default 150)
 *   maxH      float   max height in PDF points         (default 60)
 *   margin    float   fallback margin for both axes    (default 30)
 *   marginX   float   horizontal margin from page edge (default = margin)
 *   marginY   float   vertical margin from page edge   (default = margin)
 *   position  string  'bottom-left' (default), 'bottom-right', 'top-left', 'top-right'
 *   cacheDir  string  where to store normalised PNGs   (default sys_get_temp_dir())
 *
 * @return bool  true if placed, false on error (logged).
 */
function placeLogo($pdf, string $logoPath, array $opts = []): bool
{
    $maxW     = (float)  ($opts['maxW']     ?? 150);
    $maxH     = (float)  ($opts['maxH']     ?? 60);
    $margin   = (float)  ($opts['margin']   ?? 30);
    $marginX  = (float)  ($opts['marginX']  ?? $margin);
    $marginY  = (float)  ($opts['marginY']  ?? $margin);
    $position = (string) ($opts['position'] ?? 'bottom-left');
    $cacheDir = (string) ($opts['cacheDir'] ?? sys_get_temp_dir());

    // --- Sanity checks ---
    if (!is_readable($logoPath)) {
        error_log("placeLogo: cannot read logo file: $logoPath");
        return false;
    }

    $info = @getimagesize($logoPath);
    if ($info === false || $info[2] !== IMAGETYPE_PNG) {
        error_log("placeLogo: not a valid PNG: $logoPath");
        return false;
    }

    [$srcW, $srcH] = $info;
    if ($srcW <= 0 || $srcH <= 0) {
        error_log("placeLogo: zero-dimension PNG: $logoPath");
        return false;
    }

  // Do we need to remove tranparency?
  $renderPath = $logoPath;
  $needsFlattening = false;
  $colourType = -1;
  $bitDepth   = -1;

  $fh = @fopen($logoPath, 'rb');
  if ($fh !== false) {
    $header = fread($fh, 26);
    fclose($fh);
    if (strlen($header) >= 26) {
        $bitDepth   = ord($header[24]);
        $colourType = ord($header[25]);
        // ezPDF (R&OS variant) accepts: greyscale (0), RGB (2), palette (3).
        // It rejects alpha-channel types (4 = grey+alpha, 6 = RGBA).
        // Anything else, or non-8-bit, also gets flattened to be safe.
        if (in_array($colourType, [4, 6], true) || $bitDepth !== 8) {
            $needsFlattening = true;
        }
    }
  }

  if ($needsFlattening) {
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }
    $cacheKey = md5($logoPath . '|' . filemtime($logoPath));
    $cached   = $cacheDir . DIRECTORY_SEPARATOR . 'logo-' . $cacheKey . '.png';

    if (!is_file($cached)) {
        if (!flattenPngForEzpdf($logoPath, $cached)) {
            error_log("placeLogo: PNG flattening failed for $logoPath; using original");
        }
    }
    if (is_file($cached)) {
        $renderPath = $cached;
    }
  }

    // --- Calculate scaled dimensions, preserving aspect ratio, never upscaling ---
    $scale = min($maxW / $srcW, $maxH / $srcH, 1.0);
    $drawW = $srcW * $scale;
    $drawH = $srcH * $scale;

    // --- Calculate position; ezPDF origin is bottom-left ---
    $pageW = $pdf->ez['pageWidth']  ?? 595;   // A4 portrait default
    $pageH = $pdf->ez['pageHeight'] ?? 842;

    switch ($position) {
        case 'bottom-right':
            $x = $pageW - $drawW - $marginX;
            $y = $marginY;
            break;
        case 'top-left':
            $x = $marginX;
            $y = $pageH - $drawH - $marginY;
            break;
        case 'top-right':
            $x = $pageW - $drawW - $marginX;
            $y = $pageH - $drawH - $marginY;
            break;
        case 'bottom-left':
        default:
            $x = $marginX;
            $y = $marginY;
            break;
    }

    // --- Debug log (remove or comment out once placement is confirmed working) ---
    error_log(sprintf(
        'placeLogo: src=%s (%dx%d, ct=%d, bd=%d) render=%s draw=%.1fx%.1f at (%.1f,%.1f) page=%dx%d',
        $logoPath, $srcW, $srcH, $colourType, $bitDepth,
        $renderPath, $drawW, $drawH, $x, $y, $pageW, $pageH
    ));

    // --- Place it ---
    $pdf->addPngFromFile($renderPath, $x, $y, $drawW, $drawH);

    if (!empty($pdf->messages)) {
        error_log('placeLogo: ezPDF messages after addPngFromFile: ' . $pdf->messages);
    }

    return true;
}

/**
 * Re-encode any PNG to 8-bit RGB (no alpha) by flattening transparency
 * onto a background colour. Required because the bundled ezPDF/R&OS PDF
 * library supports PNG transparency only for palette images, not for
 * true-colour alpha channels.
 */
function flattenPngForEzpdf(string $src, string $dst, array $bgRgb = [255, 255, 255]): bool
{
    if (!function_exists('imagecreatefrompng')) {
        error_log('flattenPngForEzpdf: GD extension not available');
        return false;
    }

    $im = @imagecreatefrompng($src);
    if ($im === false) {
        error_log("flattenPngForEzpdf: imagecreatefrompng failed: $src");
        return false;
    }

    $w = imagesx($im);
    $h = imagesy($im);

    // Opaque background canvas in the requested colour.
    $canvas = imagecreatetruecolor($w, $h);
    $bg = imagecolorallocate($canvas, $bgRgb[0], $bgRgb[1], $bgRgb[2]);
    imagefilledrectangle($canvas, 0, 0, $w, $h, $bg);

    // Composite source over the background; alpha pixels blend with bg.
    imagealphablending($canvas, true);
    imagecopy($canvas, $im, 0, 0, 0, 0, $w, $h);

    // Save as RGB (no alpha channel at all).
    imagesavealpha($canvas, false);
    $ok = imagepng($canvas, $dst);

    imagedestroy($im);
    imagedestroy($canvas);

    return $ok !== false;
}

function tempdir(?string $dir = null, string $prefix = 'zabbix_report_'): string
{
    $tempfile = tempnam($dir ?? sys_get_temp_dir(), $prefix);
    if ($tempfile === false) {
        throw new RuntimeException('tempnam() failed');
    }
    if (file_exists($tempfile)) {
        unlink($tempfile);
    }
    $oldUmask = umask(0);
    if (!mkdir($tempfile, 0o775) && !is_dir($tempfile)) {
        umask($oldUmask);
        throw new RuntimeException('mkdir failed: ' . $tempfile);
    }
    umask($oldUmask);
    return $tempfile;
}

function cleanup_name(string $name, string $key = ''): string
{
    global $debug;

    // Substitute $1, $2 from the item key parameters: foo[bar,baz] → $1=bar, $2=baz
    if ($key !== '' && preg_match('/\[([^\]]*)\]/', $key, $m)) {
        $params = array_map('trim', explode(',', $m[1]));
        for ($i = 1; $i <= count($params); $i++) {
            $name = str_replace('$' . $i, $params[$i - 1], $name);
        }
        if ($debug) {
            echo "Cleaned name with params: $name<p>";
        }
    }
    // Drop trailing macro placeholders like {$FOO}
    if (str_contains($name, '{')) {
        $name = preg_replace('/\{.*$/', '', $name) ?? $name;
    }
    return $name;
}

/**
 * Stable sort of a list of associative arrays by one of the inner keys.
 *
 * @param array<int,array<string,mixed>> $rows
 * @return array<int,array<string,mixed>>
 */
function maSort(array $rows, string $sortKey, bool $descending = false): array
{
    if ($sortKey === '' || count($rows) === 0) {
        return $rows;
    }
    // Use a stable sort by combining (key, original index)
    $indexed = [];
    foreach ($rows as $i => $row) {
        $indexed[] = [$row[$sortKey] ?? '', $i, $row];
    }
    usort($indexed, function ($a, $b) use ($descending) {
        $cmp = $a[0] <=> $b[0];
        if ($cmp === 0) {
            $cmp = $a[1] <=> $b[1];
        }
        return $descending ? -$cmp : $cmp;
    });
    return array_column($indexed, 2);
}

/**
 * @param array<int,array<string,mixed>> $rows
 */
function z_avg(array $rows, string $col): ?float
{
    if (count($rows) === 0) {
        return null;
    }
    $sum = 0.0;
    $n = 0;
    foreach ($rows as $row) {
        if (isset($row[$col]) && is_numeric($row[$col])) {
            $sum += (float) $row[$col];
            $n++;
        }
    }
    return $n > 0 ? $sum / $n : null;
}

function secondsToTime(int|float $seconds): string
{
    $seconds = (int) $seconds;
    if ($seconds < 0) {
        $seconds = 0;
    }
    $dtF = new DateTime('@0');
    $dtT = new DateTime('@' . $seconds);
    return $dtF->diff($dtT)->format('%a days, %h hours, %i minutes and %s seconds');
}

function percent(int|float $value): string
{
    return floor($value * 100) . ' %';
}

function formatBytes(int|float $bytes, int $precision = 2): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max((float) $bytes, 0.0);
    $pow   = $bytes > 0 ? (int) floor(log($bytes) / log(1024)) : 0;
    $pow   = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}

function formatBits(int|float $bits, int $precision = 2): string
{
    $units = ['bps', 'Kbps', 'Mbps', 'Gbps', 'Tbps'];
    $bits  = max((float) $bits, 0.0);
    $pow   = $bits > 0 ? (int) floor(log($bits) / log(1000)) : 0;
    $pow   = min($pow, count($units) - 1);
    $bits /= 1000 ** $pow;
    return round($bits, $precision) . ' ' . $units[$pow];
}

function updown(int|float|string $value): string
{
    return match ((int) $value) {
        0       => 'DOWN',
        1       => 'UP',
        2       => 'DOWN',
        default => 'Unknown (' . $value . ')',
    };
}

// ---------------------------------------------------------------------
// Value formatting helper, factored out of the original switch ladder
// ---------------------------------------------------------------------

/**
 * Format a raw item value according to the configured pseudo-type.
 *
 * @return array{0:string,1:string} [formatted_current, formatted_trend_or_empty]
 */
function format_item_values(mixed $value, ?float $trendAvg, string $type, string $unit): array
{
    // Special-cases that override the type
    if ($type === 'bits') {
        return [formatBits((float) $value), $trendAvg !== null ? formatBits($trendAvg) : ''];
    }
    if ($type === 'number' && $unit === 'B') {
        return [formatBytes((float) $value), $trendAvg !== null ? formatBytes($trendAvg) : ''];
    }

    $fmt = static function (mixed $v) use ($type, $unit): string {
        if ($v === null || $v === '') {
            return '';
        }
        return match ($type) {
            'bytes'    => formatBytes((float) $v),
            'seconds'  => secondsToTime((float) $v),
            'ms'       => ((float) $v < 8 ? round((float) $v * 1000, 2) : round((float) $v, 2)) . ' ms',
            'number'   => round((float) $v, 2) . ' ' . $unit,
            'datetime' => date('Y-m-d H:i:s', (int) $v),
            'updown'   => updown($v),
            'percent'  => percent(round((float) $v, 2)),
            default    => (string) $v,
        };
    };

    return [$fmt($value), $trendAvg !== null ? $fmt($trendAvg) : ''];
}

// ---------------------------------------------------------------------
// Main report builder
// ---------------------------------------------------------------------

function CreatePDF(array $hostarray): void
{
    global $stime, $timeperiod, $tmp_pdf_data, $z_tmpimg_path, $debug, $showdates;
    global $starttime, $endtime;
    global $TriggersOn, $GraphsOn, $ItemGraphsOn, $ItemsOn, $items, $TrendsOn, $trends, $mygraphs, $myitemgraphs;
    global $z_server, $z_user, $z_pass, $z_verify_tls;

    // One graph renderer per report run — fetches the frontend cookie once.
    $renderer = new ZabbixGraph($z_server, $z_user, $z_pass, (bool) ($z_verify_tls ?? false));
    $renderer->setDebug((bool) $debug);

    if ($debug) {
        echo "Time scope - starttime: $starttime, endtime: $endtime - stime: $stime<BR/><p>\n";
    }

    foreach ($hostarray as $host) {
        $hostid           = (int) $host['hostid'];
        $hostname         = (string) $host['name'];
        $trimmed_hostname = rawurlencode($hostname);

        if ($debug) {
            echo "<b>$hostname (id:$hostid)</b><br>\n";
        }

        $fh = fopen($tmp_pdf_data, 'a');
        if ($fh === false) {
            throw new RuntimeException("Can't open $tmp_pdf_data for writing");
        }

        // ----- System status (configured "items" list) ------------------------
        if (($ItemsOn ?? '') === 'yes') {
            fwrite($fh, "1<System Status for $hostname>\n\n");
            fwrite($fh, "#C\n");
            foreach ($items as $itemPattern => $type) {
                $rows = ZabbixAPI::fetch_array('item', 'get', [
                    'output'    => ['itemid', 'name', 'key_', 'description', 'lastclock', 'lastvalue', 'units'],
                    'hostids'   => $hostid,
                    'search'    => ['name' => $itemPattern],
                    'sortfield' => 'name',
                ]);
                $rows = maSort($rows, 'key_');
                if (count($rows) === 0) {
                    continue;
                }
                if ($debug) {
                    echo "<B>Search for: $itemPattern of type $type</B><br>\n";
                }
                foreach ($rows as $val) {
                    $name  = cleanup_name((string) $val['name'], (string) $val['key_']);
                    $unit  = (string) $val['units'];
                    [$formatted, ] = format_item_values($val['lastvalue'], null, $type, $unit);
                    $datePrefix = $showdates ? date('Y-m-d H:i:s', (int) $val['lastclock']) . ' - ' : '';
                    fwrite($fh, $datePrefix . '<b>' . $name . ' :</b> ' . $formatted . "\n");
                }
            }
            fwrite($fh, "#c\n\n");
        }

        // ----- Trends ----------------------------------------------------------
        if (($TrendsOn ?? '') === 'yes') {
            fwrite($fh, "1<Trends and metrics for $hostname>\n\n");
            fwrite($fh, "#C\n");
            foreach ($trends as $trendPattern => $type) {
                $rows = ZabbixAPI::fetch_array('item', 'get', [
                    'output'    => ['itemid', 'name', 'key_', 'description', 'lastclock', 'lastvalue', 'units'],
                    'hostids'   => $hostid,
                    'search'    => ['name' => $trendPattern],
                    'sortfield' => 'name',
                ]);
                $rows = maSort($rows, 'key_');
                if (count($rows) === 0) {
                    continue;
                }
                foreach ($rows as $val) {
                    $name = cleanup_name((string) $val['name'], (string) $val['key_']);
                    $unit = (string) $val['units'];

                    $trendRows = ZabbixAPI::fetch_array('trend', 'get', [
                        'output'    => ['itemid', 'num', 'value_min', 'value_avg', 'value_max'],
                        'itemids'   => (int) $val['itemid'],
                        'time_from' => (int) $starttime,
                        'time_till' => (int) $endtime,
                    ]);
                    $trendAvg = z_avg($trendRows, 'value_avg');

                    [$formattedNow, $formattedTrend] = format_item_values($val['lastvalue'], $trendAvg, $type, $unit);
                    $datePrefix = $showdates ? date('Y-m-d H:i:s', (int) $val['lastclock']) . ' - ' : '';

                    fwrite($fh, $datePrefix . '<b>' . $name . ' :</b> latest value: ' . $formattedNow . "\n");
                    if (count($trendRows) > 1 && $trendAvg !== null) {
                        fwrite($fh, $datePrefix . '<b>' . $name . '</b> trend/SLA: ' . $formattedTrend
                            . ' (' . count($trendRows) . " data points)\n");
                    } else {
                        fwrite($fh, $datePrefix . '<b>' . $name . "</b> trend/SLA: no trend data found\n");
                    }
                }
            }
            fwrite($fh, "#c\n\n");
        }

        // ----- Problem events (path 2: modern problem.get / event.get) --------
        if (($TriggersOn ?? '') === 'yes') {
            // Pull problem events that occurred during the report window.
            $events = ZabbixAPI::fetch_array('event', 'get', [
                'output'              => ['eventid', 'objectid', 'clock', 'r_eventid', 'name', 'severity', 'acknowledged'],
                'source'              => 0,             // 0 = trigger
                'object'              => 0,             // 0 = trigger
                'hostids'             => $hostid,
                'time_from'           => (int) $starttime,
                'time_till'           => (int) $endtime,
                'value'               => 1,             // 1 = problem (skip recoveries here; we'll join them)
                'selectAcknowledges'  => ['clock', 'message', 'action', 'userid'],
                'selectHosts'         => ['hostid', 'name'],
                'sortfield'           => ['clock'],
                'sortorder'           => 'ASC',
            ]);

            if (count($events) > 0) {
                fwrite($fh, "1<Problem events for $hostname>\n\n");
                fwrite($fh, "#C\n");

                // For each problem event, look up its recovery time (if any).
                $recoveryIds = [];
                foreach ($events as $ev) {
                    if (!empty($ev['r_eventid']) && $ev['r_eventid'] !== '0') {
                        $recoveryIds[] = (int) $ev['r_eventid'];
                    }
                }
                $recoveries = [];
                if (count($recoveryIds) > 0) {
                    $recoveryRows = ZabbixAPI::fetch_array('event', 'get', [
                        'output'   => ['eventid', 'clock'],
                        'eventids' => $recoveryIds,
                    ]);
                    foreach ($recoveryRows as $r) {
                        $recoveries[(int) $r['eventid']] = (int) $r['clock'];
                    }
                }

                $severityNames = [
                    0 => 'Not classified',
                    1 => 'Information',
                    2 => 'Warning',
                    3 => 'Average',
                    4 => 'High',
                    5 => 'Disaster',
                ];

                foreach ($events as $ev) {
                    $tProblem = (int) $ev['clock'];
                    $tRecover = !empty($ev['r_eventid']) && isset($recoveries[(int) $ev['r_eventid']])
                        ? $recoveries[(int) $ev['r_eventid']]
                        : null;
                    $duration = $tRecover !== null ? secondsToTime($tRecover - $tProblem) : 'still active';
                    $sev      = $severityNames[(int) $ev['severity']] ?? '?';
                    $name     = (string) $ev['name'];

                    fwrite($fh, '[' . date('Y-m-d H:i:s', $tProblem) . '] '
                        . '<b>[' . $sev . ']</b> ' . $name
                        . '  (duration: ' . $duration . ")\n");

                    if (!empty($ev['acknowledges'])) {
                        foreach ($ev['acknowledges'] as $ack) {
                            $ackTime = date('Y-m-d H:i:s', (int) $ack['clock']);
                            $msg     = trim((string) ($ack['message'] ?? ''));
                            if ($msg !== '') {
                                fwrite($fh, "  <b>Acknowledged $ackTime:</b> $msg\n");
                            }
                        }
                    }
                }
                fwrite($fh, "\n#c\n\n");
            }
        }

        // ----- Graphs ----------------------------------------------------------
        if (($GraphsOn ?? '') === 'yes') {
            if (($TriggersOn ?? '') === 'yes' || ($ItemsOn ?? '') === 'yes' || ($TrendsOn ?? '') === 'yes') {
                fwrite($fh, "#NP\n");
            }
            fwrite($fh, "1<Graphs for $hostname>\n\n");

            $hostGraphs = ZabbixAPI::fetch_array('graph', 'get', [
                'output'  => ['graphid', 'name'],
                'hostids' => $hostid,
            ]);
            $hostGraphs = maSort($hostGraphs, 'name');

            $count    = 0;
            $rendered = 0;
            foreach ($hostGraphs as $g) {
                $graphid   = (int) $g['graphid'];
                $graphname = cleanup_name((string) $g['name']);

                if ($mygraphs !== '' && !preg_match($mygraphs, $graphname)) {
                    if ($debug) {
                        echo "$graphname (id:$graphid) did not match — skipping.<br>\n";
                    }
                    continue;
                }
                $imageFile = $z_tmpimg_path . '/' . $trimmed_hostname . '_' . $graphid . '.png';
                fwrite($fh, "2<$graphname>\n");
                fwrite($fh, "[$imageFile]\n");
                if (!$renderer->saveGraph($graphid, (int) $starttime, (int) $endtime, 750, 150, $imageFile)) {
                    fwrite($fh, "(graph could not be rendered)\n");
                }
                $rendered++;
                $count++;
                if ($count === 3) {
                    fwrite($fh, "#NP\n");
                    $count = 0;
                }
            }
            if ($rendered === 0) {
                fwrite($fh, "No matching graphs found. Maybe tune the setting?\n");
            } else {
                fwrite($fh, "#NP\n");
            }
        }

        // ----- Item graphs -----------------------------------------------------
        if (($ItemGraphsOn ?? '') === 'yes') {
            fwrite($fh, "1<Item Graphs for $hostname>\n\n");

            $itemRows = ZabbixAPI::fetch_array('item', 'get', [
                'output'  => ['itemid', 'name', 'key_'],
                'hostids' => $hostid,
                'filter'  => ['value_type' => [0, 3]],   // float, unsigned int
            ]);
            $itemRows = maSort($itemRows, 'name');

            $count    = 0;
            $rendered = 0;
            foreach ($itemRows as $it) {
                $itemid    = (int) $it['itemid'];
                $graphname = cleanup_name((string) $it['name'], (string) $it['key_']);

                if ($myitemgraphs !== '' && !preg_match($myitemgraphs, $graphname)) {
                    continue;
                }
                $imageFile = $z_tmpimg_path . '/' . $trimmed_hostname . '_' . $itemid . '.png';
                fwrite($fh, "2<$graphname>\n");
                fwrite($fh, "[$imageFile]\n");
                if (!$renderer->saveItemGraph($itemid, (int) $starttime, (int) $endtime, 750, 150, $imageFile)) {
                    fwrite($fh, "(graph could not be rendered)\n");
                }
                $rendered++;
                $count++;
                if ($count === 3) {
                    fwrite($fh, "#NP\n");
                    $count = 0;
                }
            }
            if ($rendered === 0) {
                fwrite($fh, "No items found to graph. Maybe tune the setting?\n");
            } else {
                fwrite($fh, "#NP\n");
            }
        }

        fwrite($fh, "#NP\n");
        fclose($fh);
    }
}
?>
