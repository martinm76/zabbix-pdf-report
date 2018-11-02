<?php
//CONFIGURABLE
$user_login=1; // If $user_login is 0, use credentials below and don't prompt for login.
//$user_login=0; // If $user_login is 0, use credentials below and don't prompt for login.
$allow_localhost=1; // If a request is made from 127.0.0.1, use credentials below in createpdf.php

$version	= '1.1.1';

// What items would you like to see in the report? Things that do not match are excluded automatically.
$items = array('system information'=>'string','uptime'=>'seconds', 'boot time'=>'datetime', 'total memory'=>'bytes', 'available memory'=>'bytes', 'Free disk space'=>'number', 'Free swap space in %'=>'number', 'version of zabbix_agent'=>'string', 'services'=>'string', 'update'=>'number','certificate'=>'string','advanced ntp'=>'string','Interface speed'=>'bits','Operational status'=>'updown','Alias of interface'=>'string','certificate'=>'string');

// Which items would you like to see overall statistics for over the selected period? Presently only avg is shown, but min and max could easily be added.
$trends = array('ICMP ping'=>'updown','ICMP loss'=>'number', 'ICMP response'=>'ms', 'BatteryCharge'=>'number', 'Battery capacity'=>'number', 'voltage'=>'number', 'output power'=>'number', 'CPU Idle Time'=>'number', '15 min average'=>'number', 'Temp'=>'number', 'Watt'=>'number', 'uptime'=>'seconds','transactions per second'=>'number');

$showdates = false; // Prepend date and time on items and trends, or leave it out? false = leave it out.
//$showdates = true; // Prepend date and time on items and trends, or leave it out? false = leave it out.

// Would you like to limit what graphs are displayed? Enter partial matches (or complete names) here.
// $mygraphs = '#.*#'; // Match all graphs
$mygraphs = '#(Ping|CPU load|CPU usage|CPU util|processor|Disk space|Swap|Ethernet|Memory usage|^Traffic on |traffic on eth)#';
$myitemgraphs = '#(Utilization of|farm connection|Average Latency|Number of processes|Cache % Hit)#';

# zabbix server info(user must have API access)
// $z_server 	= 'http://localhost/zabbix/';
$z_server 	= 'https://YourServerHere/zabbix/'; // Replace YourServerHere with either en IP or an FDQN (e.g. zabbix.company.com). Remove the s in https if for some reason you don't use https yet. Or better yet, get Let's Encrypt installed and use https!
$z_user		= 'Admin';
$z_pass		= 'YourPasswordHere';


# Temporary directory for storing pdf data and graphs - must exist
$z_tmp_path	= './tmp';
# Directory for storing PDF reports
$pdf_report_dir	= './reports';
# Root URL to reports
#$pdf_report_url	= $z_server ."report/reports";
$pdf_report_url	= "./reports";
# paper settings
$paper_format	= 'A4'; // formats supported: 4A0, 2A0, A0 -> A10, B0 -> B10, C0 -> C10, RA0 -> RA4, SRA0 -> SRA4, LETTER, LEGAL, EXECUTIVE, FOLIO
$paper_orientation = 'portrait'; // formats supported: portrait / landscape
# time zone - see http://php.net/manual/en/timezones.php
$timezone	= 'Europe/Oslo';
# Logo used in PDF - may be empty
# TODO: Specify image size!
$pdf_logo	= './images/general/zabbix.png';
$company_name   = 'YourCompany Name';

//DO NOT CHANGE BELOW THIS LINE
$z_tmp_cookies 	= "/tmp/";
$z_url_index 	= $z_server ."index.php";
$z_url_graph	= $z_server ."chart2.php";
$z_item_graph	= $z_server ."chart.php";
$z_url_api	= $z_server ."api_jsonrpc.php";
$z_login_data	= "name=" .$z_user ."&password=" .$z_pass ."&autologin=1&enter=Sign+in";
?>
