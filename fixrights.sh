#!/bin/sh

if [ ! -e tmp ] ; then 
  mkdir tmp
fi

if [ ! -e reports ] ; then
  mkdir reports
fi

chmod 777 tmp reports

ls -ld tmp reports
echo "You should now have tmp and reports folders that your webserver can write to."
