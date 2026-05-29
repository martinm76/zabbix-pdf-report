<?php
/**
 * zabbix-pdf-report — 2.x
 * Helpers for the chooser / index pages.
 */
declare(strict_types=1);

/**
 * @param array<int,array<string,mixed>> $rows
 */
function ReadArray(array $rows): void
{
    foreach ($rows as $row) {
        $name = (string) ($row['name'] ?? '');
        $id   = $row['hostid'] ?? $row['groupid'] ?? $name;
        echo '<option value="' . htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "</option>\n";
    }
}

/**
 * @param array<mixed> $array
 * @return array<int,mixed>
 */
function array_flatten(array $array): array
{
    $output = [];
    array_walk_recursive($array, static function ($current) use (&$output): void {
        $output[] = $current;
    });
    return $output;
}

/**
 * @return array<string,string>  [mtime,filename → filename]
 */
function listdir_by_date(string $path): array
{
    $list = [];
    $dh = @opendir($path);
    if ($dh === false) {
        return $list;
    }
    while (($file = readdir($dh)) !== false) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        $list[filemtime("$path/$file") . ',' . $file] = $file;
    }
    closedir($dh);
    krsort($list);
    return $list;
}

function ListOldReports(string $dir): void
{
    global $z_user, $hosts, $host_groups;

    echo "<thead><tr><th>Report timestamp</th><th align=\"left\">Report</th></tr></thead>\n<tbody>\n";
    foreach (listdir_by_date($dir) as $fdate => $fname) {
        $parts = explode(',', $fdate, 2);
        $stamp = date('Y.m.d H:i:s', (int) $parts[0]);
        $name  = substr(rawurldecode($fname), 0, -4);
        $url   = rawurlencode($fname);

        $hostsFlat  = is_array($hosts ?? null)       ? array_flatten($hosts)       : [];
        $groupsFlat = is_array($host_groups ?? null) ? array_flatten($host_groups) : [];

        if (in_array($name, $hostsFlat, true) || in_array($name, $groupsFlat, true)) {
            $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
            echo "<tr><td>$stamp</td><td align=\"left\"><a href=\"reports/$url\">$safeName</a></td></tr>\n";
        }
    }
    echo "</tbody>\n";
}

?>
