<?php

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

$dir_files = listdir_by_date("reports");

$dn = dirname("reports");

foreach ($dir_files as $fdate => $fname) {
	$fdate = explode(",",$fdate);
	$fdate = date("Y.m.d H:i:s", $fdate[0]);
	$name=substr(str_replace("_"," ",$fname), 0, -4);
	$name=substr(str_replace("--","/",$name), 0, 99);
	$url=rawurlencode($name);
	if ( $name != $url ) {
		echo "$fname should be $url.pdf";
		rename("./reports/".$fname, "./reports/".$url.".pdf");
		echo " - Done!\n";
	}

}

?>
