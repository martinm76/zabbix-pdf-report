#!/bin/sh

if [ $UID -ne 0 ] ; then
  echo "You must run this script as root."
  exit 1
fi

wwwproc=$(netstat -tpln | grep -e :80 -e :443 | grep / | tail -1 | cut -c80- | cut -d"/" -f1) # PID of process running the webserver
wwwsubproc=$(pstree -p $wwwproc | tail -1 | cut -d"(" -f2 | cut -d")" -f1) # Find a child process, if possible
wwwuser=$(ps u $wwwsubproc | tail -1 | cut -d" " -f1) # Hopefully the user running apache


if [ "$wwwproc" != "root" ] ; then
  wwwuser="apache" # Educated guess
fi

if [ ! -e tmp ] ; then 
  mkdir tmp
fi

if [ ! -e reports ] ; then
  mkdir reports
fi

chmod 777 tmp reports

chown -R $wwwuser * 

ls -ld tmp reports

# Check for SELinux
if [ "$(which getenforce)" != "" ] ; then
	echo ""
	echo "SELinux seems to be installed. We will try to fix context. It may not be enough ..."

        # SELinux serve files off Apache, resursive
        chcon -t httpd_sys_content_t $(pwd) -R
 
        # Fix anything SELinux might be cross about
  	restorecon -vvR $(pwd)

        # Allow write only to specific dirs
        chcon -t httpd_sys_rw_content_t $(pwd)/reports -R
        chcon -t httpd_sys_rw_content_t $(pwd)/tmp -R
fi

echo ""
echo "You should now have tmp and reports folders that your webserver can write to."
