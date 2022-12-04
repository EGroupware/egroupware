#!/bin/bash
set -e

VERSION=${VERSION:-dev-master}
PHP_VERSION=${PHP_VERSION:-7.4}

# if EGW_APC_SHM_SIZE is set in environment, propagate value to apcu.ini, otherwise set default of 128M
grep "apc.shm_size" /etc/php/$PHP_VERSION/fpm/conf.d/20-apcu.ini >/dev/null && \
  sed -e "s/^;\?apc.shm_size.*/apc.shm_size=${EGW_APC_SHM_SIZE:-128M}/g" \
    -i /etc/php/$PHP_VERSION/fpm/conf.d/20-apcu.ini || \
  echo "apc.shm_size=${EGW_APC_SHM_SIZE:-128M}" >> /etc/php/$PHP_VERSION/fpm/conf.d/20-apcu.ini

# if EGW_SESSION_TIMEOUT is set in environment, propagate value to php.ini
test -n "$EGW_SESSION_TIMEOUT" && test "$EGW_SESSION_TIMEOUT" -ge 1440 && \
	sed -e "s/^;\?session.gc_maxlifetime.*/session.gc_maxlifetime=$EGW_SESSION_TIMEOUT/g" \
		-i /etc/php/$PHP_VERSION/fpm/php.ini

# if EGW_MEMORY_LIMIT is set in environment, propagate value to pool.d/www.conf, which has higher precedence then php.ini
test -n "$EGW_MEMORY_LIMIT" && \
	sed -e "s/^;\?php_admin_value\[memory_limit\].*/php_admin_value[memory_limit]=$EGW_MEMORY_LIMIT/g" \
		-i /etc/php/$PHP_VERSION/fpm/pool.d/www.conf

# if EGW_MAX_EXECUTION_TIME is set in environment, propagate value to php.ini
test -n "$EGW_MAX_EXECUTION_TIME" && test "$EGW_MAX_EXECUTION_TIME" -ge 90 && \
	sed -e "s/^;\?max_execution_time.*/max_execution_time=$EGW_MAX_EXECUTION_TIME/g" \
		-i /etc/php/$PHP_VERSION/fpm/php.ini

# ToDo check version before copy
rsync -a --delete /usr/share/egroupware-sources/ /usr/share/egroupware/

# exclude deprecated apps from old packages (not installed via git), always exclude sitemgr
test "$PHP_VERSION" = "7.4" || {
  EXCLUDE="--exclude sitemgr"
  for app in phpgwapi etemplate wiki phpbrain
  do
    [ -d /usr/share/egroupware-extra/$app -a ! -d /usr/share/egroupware-extra/$app/.git ] && \
      EXCLUDE="$EXCLUDE --exclude $app"
  done
}
# sources of extra apps merged into our sources (--ignore-existing to NOT overwrite any regular sources!)
test -d /usr/share/egroupware-extra && \
	rsync -a --ignore-existing --exclude .git $EXCLUDE /usr/share/egroupware-extra/ /usr/share/egroupware/

# check and if necessary change ownership of /var/lib/egroupware and our header.inc.php
test $(stat -c '%U' /var/lib/egroupware) = "www-data" || \
	chown -R www-data:www-data /var/lib/egroupware
test -f /var/lib/egroupware/header.inc.php &&
	chown www-data:www-data /var/lib/egroupware/header.inc.php &&
	chmod 600 /var/lib/egroupware/header.inc.php

# add private CA so egroupware can validate your certificate to talk to Collabora or Rocket.Chat
test -f /usr/local/share/ca-certificates/private-ca.crt && {
	update-ca-certificates
	sed 's#;\?openssl.cafile.*#openssl.cafile = /etc/ssl/certs/ca-certificates.crt#g' -i /etc/php/$PHP_VERSION/fpm/php.ini
}

# write install-log in /var/lib/egroupware (only readable by root!)
LOG=/var/lib/egroupware/egroupware-docker-install.log
touch $LOG
chmod 600 $LOG

max_retries=10
export try=0
# EGW_SKIP_INSTALL=true skips initial installation (no header.inc.php yet)
until [ -n "$EGW_SKIP_INSTALL" -a ! -f /var/lib/egroupware/header.inc.php ] || \
	sudo -u www-data php /usr/share/egroupware/doc/rpm-build/post_install.php \
	--start_webserver "" --autostart_webserver "" \
	--start_db "" --autostart_db "" \
	--db_type "${EGW_DB_TYPE:-mysqli}" \
	--db_host "${EGW_DB_HOST:-localhost}" \
	--db_grant_host "${EGW_DB_GRANT_HOST:-localhost}" \
	--db_root "${EGW_DB_ROOT:-root}" \
	--db_root_pw   "${EGW_DB_ROOT_PW:-}" \
	--db_name "${EGW_DB_NAME:-egroupware}" \
	--db_user "${EGW_DB_USER:-egroupware}" \
	--db_pass "${EGW_DB_PASS:-}"
do
	if [ "$try" -gt "$max_retries" ]; then
		echo "Installing of EGroupware failed!"
		break
	fi
	echo "Retrying EGroupware installation in 3 seconds ..."
	try=$((try+1))
	sleep 3s
done 2>&1 | tee -a $LOG

# as we can NOT exit from until (runs a subshell), we need to check and do it here
[ "$(tail -1 $LOG)" = "Installing of EGroupware failed!" ] && exit 1

# fix cron entries in case docker uses "overlay" storage driver (eg. Univention 4.4)
# cron does NOT executing scripts with "NUMBER OF HARD LINKS > 1"
for f in /etc/crontab /etc/cron.*/*; do
  [ $(ls -l $f | cut -d' ' -f2) -gt 1 ] && {
    mv $f /tmp
    cat /tmp/$(basename $f) > $f
  }
done
# to run async jobs
service cron start

exec "$@"