zabbix-dynamic-report-generation
================================
Please run ./fixrights.sh after you have checked out this repo. By default, reports and tmp folders will not exist and need to be created.
They also need to be writable by the webserver. At present they are writable by everyone. Patches welcome.

If in doubt: mkdir tmp reports; chmod 777 tmp reports

I have tried to prepare fixrights.sh for SELinux being active on the system. It may or may not be enough to allow report generation.
If you fail to get the PDF's generated, try disabling SELinux for a while:

```
setenforce 0
```

You need various PHP modules installed for this to work. You may often have to install these yourself:

php-curl 
php-json

(package name may vary and in some cases include a PHP version)

New User
========
Copy config.inc.php.dist to config.inc.php and edit it to fit your environment. It should be fairly well documented internally.

Existing User
=============
Check the changes in config.inc.php.dist, if any, against your local copy and port them over, or make your changes again like the New User section.

Remember to adjust config.inc.php to match your company, server and location. The dist file has dummy values that will not work on its own.

Follow the discussion here:
https://www.zabbix.com/forum/showthread.php?t=24998
