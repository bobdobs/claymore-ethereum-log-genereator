#! /bin/sh
#
# Installation
# - Edit the file and update DAEMON_OPTS to match where you placed `eth_log_gen.php`
# - Make sure you have set RUN_INDEFINITELY=true in `eth_log_gen.php`
#
# - Move this file to /etc/init.d/eth_php_logger.sh
# - $ sudo chmod +x /etc/init.d/eth_php_logger.sh
# - $ sudo update-rc.d eth_php_logger.sh defaults
# - $ sudo systemctl daemon-reload
#
# Starting and stopping
# - Start: `$ sudo service eth_php_logger.sh start` or `$ sudo /etc/init.d/eth_php_logger.sh start`
# - Stop: `$ sudo service eth_php_logger.sh stop` or `$ sudo /etc/init.d/eth_php_logger.sh stop`
#
#
# Need to do this: http://forum.directadmin.com/showthread.php?t=42174
#


NAME=eth_php_logger
DESC="Eth PHP log generator via PHP CLI script"
PIDFILE="/var/run/${NAME}.pid"
LOGFILE="/var/log/${NAME}.log"

DAEMON="/usr/bin/php"
DAEMON_OPTS="/home/scripts/claymore-ethereum-log-genereator/eth_log_gen.php"

START_OPTS="--start --background --make-pidfile --pidfile ${PIDFILE} --exec ${DAEMON} ${DAEMON_OPTS}"
STOP_OPTS="--stop --pidfile ${PIDFILE}"

test -x $DAEMON || exit 0

set -e

case "$1" in
    start)
        echo -n "Starting ${DESC}: "
        start-stop-daemon $START_OPTS >> $LOGFILE
        echo "$NAME."
        ;;
    stop)
        echo -n "Stopping $DESC: "
        start-stop-daemon $STOP_OPTS
        echo "$NAME."
        rm -f $PIDFILE
        ;;
    restart|force-reload)
        echo -n "Restarting $DESC: "
        start-stop-daemon $STOP_OPTS
        sleep 1
        start-stop-daemon $START_OPTS >> $LOGFILE
        echo "$NAME."
        ;;
    *)
        N=/etc/init.d/$NAME
        echo "Usage: $N {start|stop|restart|force-reload}" >&2
        exit 1
        ;;
esac

exit 0