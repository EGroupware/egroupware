#!/bin/bash
set -e

# ToDo check version before copy
rsync -a --delete /usr/share/egroupware-sources/ /usr/share/egroupware/

# sources of extra apps merged into our sources (--ignore-existing to NOT overwrite any regular sources!)
test -d /usr/share/egroupware-extra && \
	rsync -a --ignore-existing /usr/share/egroupware-extra/ /usr/share/egroupware/

# check and if necessary change ownership of /var/lib/egroupware
test $(stat -c '%U' /var/lib/egroupware) = "www-data" || \
	chown -R www-data:www-data /var/lib/egroupware

# add private CA so egroupware can validate your certificate to talk to Collabora or Rocket.Chat
test -f /usr/local/share/ca-certificates/private-ca.crt &&
	update-ca-certificates

# write install-log in /var/lib/egroupware (only readable by root!)
LOG=/var/lib/egroupware/egroupware-docker-install.log
touch $LOG
chmod 600 $LOG

max_retries=10
export try=0
# EGW_SKIP_INSTALL=true skips initial installation (no header.inc.php yet)
until [ -n "$EGW_SKIP_INSTALL" -a ! -f /var/lib/egroupware/header.inc.php ] || \
	php /usr/share/egroupware/doc/rpm-build/post_install.php \
	--start_webserver "" --autostart_webserver "" \
	--start_db "" --autostart_db "" \
	--db_type "${EGW_DB_TYPE:-mysqli}" \
	--db_host "${EGW_DB_HOST:-localhost}" \
	--db_grant_host "${EGW_DB_GRANT_HOST:-%}" \
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

# to run async jobs
service cron start

exec "$@"