zabbix-dynamic-report-generation
================================
Please run ./fixrights.sh after you have checked out this repo. By default, reports and tmp folders will not exist and need to be created.
They also need to be writable by the webserver. At present they are writable by everyone. Patches welcome.

If in doubt: mkdir tmp reports; chmod 777 tmp reports

New User
========
Copy config.inc.php.dist to config.inc.php and edit it to fit your environment. It should be fairly well documented internally.

Existing User
=============
Check the changes in config.inc.php.dist, if any, against your local copy and port them over, or make your changes again like the New User section.

Remember to adjust config.inc.php to match your company, server and location. The dist file has dummy values that will not work on its own.

Follow the discussion here:
https://www.zabbix.com/forum/showthread.php?t=24998
