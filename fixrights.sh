#!/bin/sh

if [ ! -e tmp ] ; then 
  mkdir tmp
fi

if [ ! -e reports ] ; then
  mkdir reports
fi

chmod 777 tmp reports

ls -ld tmp reports

# Check for SELinux
if [ "$(which getenforce)" != "" ] ; then
	echo ""
	echo "SELinux seems to be installed. We will try to fix context. It may not be enough ..."
	restorecon -vvR $(pwd)
fi

echo ""
echo "You should now have tmp and reports folders that your webserver can write to."
