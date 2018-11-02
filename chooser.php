<?php
///////////
//
// PHP DYNAMIC DROP-DOWN BOX FOR ZABBIX PDF GENERATION
// THE IDEA BEHIND THIS IS TO CREATE A VERSION INDEPENDENT
// ADDON THAT CAN BE ADDED THROUGH SCREENS TO PREVENT BREAKAGE
//
///////////
//
// v0.1 - 1/14/12 - (c) Travis Mathis - travisdmathis@gmail.com
// Change Log: Added Form Selection, Data Gathering, Report Generation w/ basic time period selection
// pdfform.php(selection) / generatereport.php(report building) / pdf.php(report)i
// v0.2 - 2/7/12 
// Change Log: Removed mysql specific calls and replaced with API calls.  Moved config to central file
// v0.5 - 2014/09/05 - Ronny Pettersen <pettersen.ronny @ gmail.com>
//	Rewritten a lot based on original from Travis Mathis. Allows reporting on group.
//      Uses Jquery (javascript) for many of the functions on the index page.
//
///////////

include("config.inc.php");

if ( $user_login == 1 ) {
session_start();
//print_r($_SESSION);
$z_user=$_SESSION['username'];
$z_pass=$_SESSION['password'];

if ( $z_user == "" ) {
  header("Location: index.php");
}

$z_login_data	= "name=" .$z_user ."&password=" .$z_pass ."&autologin=1&enter=Sign+in";
}

global $z_user, $z_pass, $z_login_data;

require_once("inc/ZabbixAPI.class.php");
include("inc/index.functions.php");

header( 'Content-type: text/html; charset=utf-8' );
?>
<!DOCTYPE html>
<html>
<head>
	<meta http-equiv="X-UA-Compatible" content="IE=Edge"/>
	<title>Zabbix Dynamic PDF Report</title>
	<meta charset="utf-8" />
	<link rel="shortcut icon" href="/zabbix/images/general/zabbix.ico" />
	<link rel="stylesheet" type="text/css" href="css/zabbix.default.css" />
	<link rel="stylesheet" type="text/css" href="css/zabbix.color.css" />
	<link rel="stylesheet" type="text/css" href="css/zabbix.report.css" />
	<link rel="stylesheet" type="text/css" href="css/jquery.datetimepicker.min.css"/ >
	<link rel="stylesheet" type="text/css" href="css/jquery.tablesorter.pager.min.css"/ >
	<link rel="stylesheet" type="text/css" href="css/select2.css"/ >
	<script type="text/javascript" src="js/jquery-3.3.1.min.js"></script>
	<script type="text/javascript" src="js/jquery.datetimepicker.min.js"></script>
	<script type="text/javascript" src="js/jquery.validate.min.js"></script>
	<script type="text/javascript" src="js/jquery.tablesorter.combined.min.js"></script> 
	<script type="text/javascript" src="js/select2.min.js"></script> 
	<script>
		$(function(){
			$('#ReportHost').click(function(){
				$('#s_ReportHost').prop('disabled',false);
				$('#s_ReportHostGroup').prop('disabled',true);
				$('#s_ReportHost').prop('required',true);
				$('#s_ReportHostGroup').prop('required',false);
				$('#p_ReportHostGroup').hide('fast');
				$('#p_ReportHost').show('slow');
			});
			$('#ReportHostGroup').click(function(){
				$('#s_ReportHostGroup').prop('disabled',false);
				$('#s_ReportHost').prop('disabled',true);
				$('#s_ReportHostGroup').prop('required',true);
				$('#s_ReportHost').prop('required',false);
				$('#p_ReportHost').hide('fast');
				$('#p_ReportHostGroup').show('slow');
			});
			$('#RangeLast').click(function(){
				$('#s_RangeLast').prop('disabled',false);
				$('#datepicker_start').prop('disabled',true);
				$('#timepicker_start').prop('disabled',true);
				$('#datepicker_end').prop('disabled',true);
				$('#timepicker_end').prop('disabled',true);
				$('#datepicker_start').prop('required',false);
				$('#p_RangeCustom').hide('fast');
				$('#p_RangeLast').show('slow');
			});
			$('#RangeCustom').click(function(){
				$('#datepicker_start').prop('disabled',false);
				$('#timepicker_start').prop('disabled',false);
				$('#datepicker_end').prop('disabled',false);
				$('#timepicker_end').prop('disabled',false);
				$('#datepicker_start').prop('required',true);
				$('#s_RangeLast').prop('disabled',true);
				$('#s_RangeLast').prop('required',false);
				$('#p_RangeCustom').show('slow');
				$('#p_RangeLast').hide('fast');
			});
			$('#h_OldReports').click(function(){
				$(".d_OldReports").toggleClass('table-hidden');
			});
		});

		$(document).ready(function() {
			$('#s_ReportHostGroup').prop('disabled',true);
			$('#datepicker_start').prop('disabled',true);
			$('#timepicker_start').prop('disabled',true);
			$('#datepicker_end').prop('disabled',true);
			$('#timepicker_end').prop('disabled',true);
			$('#p_ReportHostGroup').hide('fast');
			$('#p_RangeCustom').hide('fast');
			$('#OldReports').tablesorter();
			$("#s_ReportHost").select2({width: "copy"});
			$("#s_ReportHostGroup").select2({width: "copy"});
			$("#s_RangeLast").select2();
		});
		</script>
</head>
<body class="originalblue">
<div id="message-global-wrap"><div id="message-global"></div></div>
<table class="maxwidth page_header" cellspacing="0" cellpadding="5"><tr><td class="page_header_l"><a class="image" href="http://www.zabbix.com/" target="_blank"><div class="zabbix_logo">&nbsp;</div></a></td><td class="maxwidth page_header_r">&nbsp;</td></tr></table>
<br/><br/>
<center><h1>Generate PDF Report</h1></center>
<br/>
<?php
// ERROR REPORTING
error_reporting(E_ALL);
set_time_limit(60);

// ZabbixAPI Connection
ZabbixAPI::debugEnabled(TRUE);
ZabbixAPI::login($z_server,$z_user,$z_pass)
	or die('Unable to login: '.print_r(ZabbixAPI::getLastError(),true));
//fetch graph data host
$hosts       = ZabbixAPI::fetch_array('host','get',array('output'=>array('hostid','name'),'sortfield'=>'host','with_graphs'=>'1','sortfield'=>'name'))
	or die('Unable to get hosts: '.print_r(ZabbixAPI::getLastError(),true));
$host_groups = ZabbixAPI::fetch_array('hostgroup','get', array('output'=>array('groupid','name'),'real_hosts'=>'1','with_graphs'=>'1','sortfield'=>'name') )
	or die('Unable to get hosts: '.print_r(ZabbixAPI::getLastError(),true));
ZabbixAPI::logout($z_server,$z_user,$z_pass)
	or die('Unable to logout: '.print_r(ZabbixAPI::getLastError(),true));

//var_dump($hosts);
//var_dump($host_group);

// Form dropdown boxes from Zabbix API Data
?>
<center>
<form class="cmxform" id="ReportForm" name="ReportForm" action='createpdf.php' method='GET'>
<table border="1" rules="NONE" frame="BOX" width="600" cellpadding="10">
<tr><td valign="middle" align="left" width="115">
<label for="ReportType"><b>Report type</b></label>
</td><td valign="center" align="left" height="30">
<p>
<input id="ReportHost" type="radio" name="ReportType" value="host" title="Generate report on HOST" checked="checked" />Host
<input id="ReportHostGroup" type="radio" name="ReportType" value="hostgroup" title="Generate report on GROUP" />Host Group
</p>
</td><td align="center" valign="top" width="110">
<center><?php echo $z_user; ?> <a href="logout.php">Logout</a></center>
</td></tr>
<tr><td valign="middle" align="left">
&nbsp;
</td><td valign="center" align="left" width="70%" height="30">
<p id="p_ReportHost">
<label for="s_ReportHost" class="error">Please select your host</label>
&nbsp;<select id="s_ReportHost" name="HostID" width="350"  style="width: 350px" title="Please select host" required>
<option value="">--&nbsp;Select&nbsp;host&nbsp;--</option>
<?php
ReadArray($hosts);
?>
</select>
</p>
<p id="p_ReportHostGroup">
&nbsp;<select id="s_ReportHostGroup" name="GroupID" width="350" style="width: 350px" title="Please select hostgroup" >
<option value="">--&nbsp;Select&nbsp;hostgroup&nbsp;--</option>
<?php
ReadArray($host_groups);
?>
</select>
</p>
<p>
<input type="checkbox" name="GraphsOn" value="yes" checked> Include graphs</input> &nbsp;
<input type="checkbox" name="ItemGraphsOn" value="yes"> Include graphed items</input> &nbsp;
<input type="checkbox" name="TriggersOn" value="yes"> Show triggers</input><BR/>
<input type="checkbox" name="ItemsOn" value="yes"> Show configured items status</input> &nbsp;
<input type="checkbox" name="TrendsOn" value="yes"> Show configured trends (SLA-ish)</input>
</p>
<p>
<input type="string" name="mygraphs2" style="font-size: 10px;"  size=80 value="<?php echo $mygraphs; ?>"> &uarr; Graphs to show (#.*# = all):</input>
<input type="string" name="myitems2" style="font-size: 10px;"  size=80 value="<?php echo $myitemgraphs; ?>"> &uarr; Items to graph (#.*# = all):</input>
</p>
</td><td valign="middle">
&nbsp;
</td></tr>
<tr><td valign="middle" align="left">
<label for="ReportRange"><b>Report range</b></label>
</td><td valign="middle" align="left">
<p>
<input id="RangeLast" type="radio" name="ReportRange" value="last" title="Report on last activity" checked="checked" />Last
<input id="RangeCustom" type="radio" name="ReportRange" value="custom" title="Report using custom report range" />Custom

</p>
</td><td valign="middle">
&nbsp;
</td></tr>
<tr><td valign="middle" align="left" height="50">
&nbsp;
</td><td valign="middle" align="left" height="50">
<p id=p_RangeLast>
&nbsp;<select id="s_RangeLast" name="timePeriod" title="Please select range" required>
<option value="Hour">Hour</option>
<option value="Day">Day</option>
<option value="Week">Week</option>
<option value="Month">Month</option>
<option value="Year">Year</option>
</select>
</p>
<p id="p_RangeCustom">
&nbsp;<b>Start:</b><input name="startdate" id="datepicker_start" type="date" size="8" />at<input name="starttime" id="timepicker_start" type="time" size="5" />
<b>End:</b><input name="enddate" id="datepicker_end" type="date" size="8" />at<input name="endtime" id="timepicker_end" type="time" size="5" />
</p>
</td><td valign="bottom" align="middle">
<input type='submit' value='Generate'>
<span class="smalltext"><input type='checkbox' name='debug'>Debug</span>
<p><center>Version: <?php echo($version); ?></center></p>
</td></tr>
</table>
</form>
<br/>
<h2 id="h_OldReports">Old reports<br>
(click to show)</h2>
</center>

<div class="d_OldReports table-hidden">
<table id="OldReports" cellpadding="0" class="tablesorter">
<?php ListOldReports($pdf_report_dir); ?>
</table>
</div>

</html></body>
<script>
jQuery(function(){
 jQuery('#datepicker_start').datetimepicker({
  dayOfWeekStart: 1,
  weeks: true,
  format:'Y/m/d',
  minDate:'-1971/01/01',// One year ago
  maxDate:'+1970/01/01',// Today is maximum date calendar
  onShow:function( ct ){
   this.setOptions({
    maxDate:jQuery('#datepicker_end').val()?jQuery('#datepicker_end').val():false
   })
  },
  timepicker: false
 });
 jQuery('#datepicker_end').datetimepicker({
  dayOfWeekStart : 1,
  weeks: true,
  format:'Y/m/d',
  minDate:'-1971/01/01',// One year ago
  maxDate:'+1970/01/01',// Today is maximum date calendar
  onShow:function( ct ){
   this.setOptions({
    minDate:jQuery('#datepicker_start').val()?jQuery('#datepicker_start').val():false
   })
  },
  timepicker: false
 });
});

jQuery('#timepicker_start').datetimepicker({ datepicker:false, format:'H:i' });
jQuery('#timepicker_end').datetimepicker({ datepicker:false, format:'H:i' });
</script>
