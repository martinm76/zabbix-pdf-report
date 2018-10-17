<?php
require_once("inc/ZabbixAPI.class.php");
include("config.inc.php");

/*
include("config.inc.php");
include("inc/index.functions.php");
include("config.php");
*/

if ( $user_login = 0 ) {
  header("Location: chooser.php");
  exit(0);
}

session_start();
if($_SERVER["REQUEST_METHOD"] == "POST")
{
// username and password sent from Form
$myusername=addslashes($_POST['username']);
$mypassword=addslashes($_POST['password']);

//session_register("myusername");
$_SESSION['login_user']=$myusername;
$_SESSION['username']=$myusername;
$_SESSION['password']=$mypassword;
//print_r($_SESSION); 
 
ZabbixAPI::debugEnabled(TRUE);
ZabbixAPI::login($z_server,$myusername,$mypassword)
	or die('Unable to login: '.print_r(ZabbixAPI::getLastError(),true));

header("location: chooser.php");
}

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
	<link rel="stylesheet" type="text/css" href="css/tablesorter.css"/ >
	<link rel="stylesheet" type="text/css" href="css/select2.css"/ >
</head>
<body class="originalblue">
<div id="message-global-wrap"><div id="message-global"></div></div>
<table class="maxwidth page_header" cellspacing="0" cellpadding="5"><tr><td class="page_header_l"><a class="image" href="http://www.zabbix.com/" target="_blank"><div class="zabbix_logo">&nbsp;</div></a></td><td class="maxwidth page_header_r">&nbsp;</td></tr></table>
<br/><br/>
<center><h1>Log in to Zabbix to Generate PDF reports</h1></center>
<br/>
<center>
<form action="" method="post">
<table border="1" rules="NONE" frame="BOX" width="250" cellpadding="10">
<tr><td valign="middle" align="right" width="115">
<label for="Username"><b>Username:</b></label>
</td><td valign="center" align="left" height="30">
<p>
<input type="text" name="username"/><br />
</p>
</td><td valign="middle" width="110">
&nbsp;
</td></tr>
<tr><td valign="middle" align="right" width="115">
<label for="Password"><b>Password:</b></label>
</td><td valign="center" align="left" height="30">
<p>
<input type="password" name="password"/><br />
</p>
</td><td valign="middle" width="110">
&nbsp;
</td></tr>
<td>&nbsp;</td><td valign="bottom" align="left">
<input type='submit' value='Sign in'>
<p>Version <?php echo($version); ?></p>
</td></tr>
</table>

<!--
<label>UserName :</label>
<label>Password :</label>
<input type="submit" value=" Submit "/><br />
-->

</form>
