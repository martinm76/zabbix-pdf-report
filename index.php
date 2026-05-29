<?php
/**
 * zabbix-pdf-report — 2.x
 * Login form. Authenticates against Zabbix once to validate credentials,
 * then forwards to chooser.php.
 */
declare(strict_types=1);

require_once __DIR__ . '/inc/ZabbixAPI.class.php';
require_once __DIR__ . '/config.inc.php';

if ((int) ($user_login ?? 1) === 0) {
    header('Location: chooser.php');
    exit;
}

session_start();

$loginError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // NEVER use addslashes() on credentials. The API class POSTs JSON, which
    // takes care of escaping. addslashes() on a password actively corrupts
    // legitimate passwords containing quotes or backslashes.
    $username = (string) ($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    ZabbixAPI::verifyTls((bool) ($z_verify_tls ?? false));

    if (ZabbixAPI::login($z_server, $username, $password)) {
        // Regenerate the session ID to defeat fixation, then store creds.
        session_regenerate_id(true);
        $_SESSION['login_user'] = $username;
        $_SESSION['username']   = $username;
        // We still need the password later for the frontend cookie used by
        // chart2.php / chart.php — that endpoint does not accept API tokens.
        // If you'd prefer not to store the password in $_SESSION, switch to
        // API-token mode in config.inc.php instead.
        $_SESSION['password']   = $password;
        ZabbixAPI::logout();
        header('Location: chooser.php');
        exit;
    }

    $loginError = ZabbixAPI::getLastError();
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="X-UA-Compatible" content="IE=Edge"/>
    <title>Zabbix Dynamic PDF Report</title>
    <meta charset="utf-8" />
    <link rel="shortcut icon" href="/zabbix/favicon.ico" />
    <link rel="stylesheet" type="text/css" href="css/zabbix.default.css" />
    <link rel="stylesheet" type="text/css" href="css/zabbix.color.css" />
    <link rel="stylesheet" type="text/css" href="css/zabbix.report.css" />
    <link rel="stylesheet" type="text/css" href="css/tablesorter.css" />
    <link rel="stylesheet" type="text/css" href="css/select2.css" />
</head>
<body class="originalblue">
<div id="message-global-wrap"><div id="message-global"></div></div>
<table class="maxwidth page_header" cellspacing="0" cellpadding="5">
<tr><td class="page_header_l">
    <a class="image" href="https://www.zabbix.com/" target="_blank" rel="noopener">
        <div class="zabbix_logo">&nbsp;</div>
    </a>
</td><td class="maxwidth page_header_r">&nbsp;</td></tr>
</table>
<br/><br/>
<center><h1>Log in to Zabbix to Generate PDF reports</h1></center>
<?php if ($loginError !== null): ?>
<center>
    <p style="color:#c00;">
        <b>Login failed:</b>
        <?php echo htmlspecialchars(is_array($loginError) ? json_encode($loginError) : (string) $loginError, ENT_QUOTES, 'UTF-8'); ?>
    </p>
</center>
<?php endif; ?>
<br/>
<center>
<form action="" method="post">
<table border="1" rules="NONE" frame="BOX" width="250" cellpadding="10">
<tr>
    <td valign="middle" align="right" width="115"><label for="username"><b>Username:</b></label></td>
    <td valign="center" align="left" height="30"><p><input type="text" id="username" name="username" autofocus required /></p></td>
    <td valign="middle" width="110">&nbsp;</td>
</tr>
<tr>
    <td valign="middle" align="right" width="115"><label for="password"><b>Password:</b></label></td>
    <td valign="center" align="left" height="30"><p><input type="password" id="password" name="password" required /></p></td>
    <td valign="middle" width="110">&nbsp;</td>
</tr>
<tr>
    <td>&nbsp;</td>
    <td valign="bottom" align="left">
        <input type="submit" value="Sign in">
        <p>Version <?php echo htmlspecialchars((string) ($version ?? '2.0.0'), ENT_QUOTES, 'UTF-8'); ?></p>
    </td>
    <td>&nbsp;</td>
</tr>
</table>
</form>
</center>
</body>
</html>

