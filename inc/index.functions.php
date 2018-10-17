<?php
// FUNCTIONS
function ReadArray($array) {
	foreach($array as $key=>$value) {
		$name = $array[$key]['name'];
		if (isset($array[$key]['hostid'])) {
			$id   = $array[$key]['hostid'];
		}
		elseif (isset($array[$key]['groupid'])) {
			$id   = $array[$key]['groupid'];
		}
		else {
			$id   = $name;
		}
		echo "<option value=\"$id\">$name</option>\n";
	}
}

function array_flatten($array) {

	$output = array();
	array_walk_recursive($array, function ($current) use (&$output) {
    $output[] = $current;
});
   return $output;
}

function listdir_by_date($path){
    $dir = opendir($path);
    $list = array();
    while($file = readdir($dir)){
        if ($file != '.' and $file != '..'){
            // add the filename, to be sure not to
            // overwrite a array key
            $ctime = filemtime("$path/$file") . ',' . $file;
            $list[$ctime] = $file;
        }
    }
    closedir($dir);
    krsort($list);
    return $list;
}

function ListOldReports($dir) {
	global $z_user, $hosts, $host_groups;
	#$dir_files = array_diff(scandir($dir), array('..', '.'));
	$dir_files = listdir_by_date($dir);
	echo "<thead>";
	echo "<tr><th>Report timestamp</th><th align=\"left\">Report</th></tr>\n";
	echo "</thead>";
	echo "<tbody>";
	foreach ($dir_files as $fdate => $fname) {
		$fdate = explode(",",$fdate);
		$fdate = date("Y.m.d H:i:s", $fdate[0]);
		$name=substr(str_replace("_"," ",$fname), 0, -4);
		$name=substr(str_replace("--","/",$fname), 0, -4);

		if ((in_array($name, array_flatten($hosts)) or (in_array($name, array_flatten($host_groups))))) {
			echo "<tr><td>$fdate</td><td align=\"left\"><a href=\"reports/$fname\">$name</a></td></tr>\n";
		}
	}
	echo "</tbody>";
}
?>
