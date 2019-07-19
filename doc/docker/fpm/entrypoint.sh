#!/bin/bash
set -e
set -x

# ToDo check version before copy
rsync -a --delete /usr/share/egroupware-sources/ /usr/share/egroupware/

# sources of extra apps merged into our sources (--ignore-existing to NOT overwrite any regular sources!)
test -d /usr/share/egroupware-extra && \
	rsync -a --ignore-existing /usr/share/egroupware-extra/ /usr/share/egroupware/

# check and if necessary change ownership of /var/lib/egroupware
test $(stat -c '%U' /var/lib/egroupware) = "www-data" || \
	chown -R www-data:www-data /var/lib/egroupware

# write install-log in /var/lib/egroupware (only readable by root!)
LOG=/var/lib/egroupware/egroupware-docker-install.log
touch $LOG
chmod 600 $LOG

max_retries=10
try=0
until php /usr/share/egroupware/doc/rpm-build/post_install.php \
	--start_webserver "" --autostart_webserver "" \
	--start_db "" --autostart_db "" \
	--db_type "${EGW_DB_TYPE:-mysqli}" \
	--db_host "${EGW_DB_HOST:-localhost}" \
	--db_grant_host "${EGW_DB_GRANT_HOST:-%}" \
	--db_root "${EGW_DB_ROOT:-root}" \
	--db_root_pw   "${EGW_DB_ROOT_PW:-}" \
	--db_name "${EGW_DB_NAME:-egroupware}" \
	--db_user "${EGW_DB_USER:-egroupware}" \
	--db_pass "${EGW_DB_PASS:-}" || [ "$try" -gt "$max_retries" ]
do
	echo "Retrying EGroupware installation in 3 seconds ..."
	try=$((try+1))
	sleep 3s
done 2>&1 | tee -a $LOG

if [ "$try" -gt "$max_retries" ]; then
	echo "Installing of EGroupware failed!" | tee -a $LOG
	exit 1
fi

# to run async jobs
service cron start

exec "$@"