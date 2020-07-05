#!/bin/sh

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
	restorecon -vvR $(pwd)
fi

echo ""
echo "You should now have tmp and reports folders that your webserver can write to."
