#!/bin/bash
set -ex

VERSION=${VERSION:-dev-master}
PHP_VERSION=${PHP_VERSION:-7.4}

# if EGW_SESSION_TIMEOUT is set in environment, propagate value to php.ini
test -n "$EGW_SESSION_TIMEOUT" && test "$EGW_SESSION_TIMEOUT" -ge 1440 &&
	sed -e "s/^;\?session.gc_maxlifetime.*/session.gc_maxlifetime=$EGW_SESSION_TIMEOUT/g" \
		-i /etc/php/$PHP_VERSION/fpm/php.ini

# if XDEBUG_REMOTE_HOST is set, patch it into xdebug config
test -n "$XDEBUG_REMOTE_HOST" && \
	sed -e "s/^xdebug.client_host.*/xdebug.client_host=$XDEBUG_REMOTE_HOST/g" \
		-i /etc/php/$PHP_VERSION/fpm/conf.d/*xdebug.ini

# installation fails without git identity
git config --global user.email || git config --global user.email "you@example.com"

# install EGroupware sources, if not already there
[ -f /var/www/egroupware/header.inc.php ] || {
	cd /var/www \
	&& ln -sf egroupware/api/templates/default/images/favicon.ico \
	&& composer.phar create-project --prefer-source --keep-vcs --no-scripts egroupware/egroupware:$VERSION \
	&& cd egroupware \
	&& ./install-cli.php \
	&& ln -sf /var/lib/egroupware/header.inc.php \
	&& sed -e 's/apache/www-data/' -e 's|/usr/share|/var/www|g' doc/rpm-build/egroupware.cron > /etc/cron.d/egroupware
}

# check if we have further apps to install (EPL or old ones ...)
cd /var/www/egroupware
for url in $(env|grep ^EGW_EXTRA_APP|cut -d= -f2)
do
	app=$(basename $url .git)
	[ $app == "epl" ] && app=stylite
	[ -d $app ] || {
		git clone $url $app \
		&& (cd $app; git remote set-url --push origin $(echo $url|sed 's|https://github.com/|git@github.com:|')) \
		&& [ -f header.inc.php ] && doc/rpm-build/post_install.php --install-app $(basename $url .git) \
		|| true # do not stop, if one clone fails
	}
done

# install phpMyAdmin sources, if not already there
[ -d /var/www/phpmyadmin ] || {
	cd /var/www \
	&& composer.phar create-project --prefer-source --keep-vcs --no-scripts phpmyadmin/phpmyadmin \
	&& cd phpmyadmin \
	&& yarn install || true
}
[ -f /var/www/phpmyadmin/config.inc.php ] || {
	cd /var/www/phpmyadmin \
	&& blowfish_secret=$(php -r "echo base64_encode(random_bytes(24));") \
	&& sed -e "s/localhost/db/g" \
		-e "s/cfg\['blowfish_secret'\] = '';/cfg['blowfish_secret'] = '$blowfish_secret';/g" \
		config.sample.inc.php > config.inc.php
}

# create data directory
[ -d /var/lib/egroupware/default ] || {
	mkdir -p /var/lib/egroupware/default/files/sqlfs \
	&& mkdir -p /var/lib/egroupware/default/backup \
	&& chown -R www-data:www-data /var/lib/egroupware \
	&& chmod 700 /var/lib/egroupware/
}

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
until [ "$EGW_SKIP_INSTALL" = "allways" -o -n "$EGW_SKIP_INSTALL" -a ! -f /var/www/egroupware/header.inc.php ] || \
	php /var/www/egroupware/doc/rpm-build/post_install.php \
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

[ "$(git config --global user.email)" == "you@example.com" ] && {
	echo "No git user set, please do so by running:"
	echo "git config --global user.email "your@email.address"
	echo "git config --global user.name "Your Name"
}

# as we can NOT exit from until (runs a subshell), we need to check and do it here
[ "$(tail -1 $LOG)" = "Installing of EGroupware failed!" ] && exit 1

# to run async jobs
service cron start

exec php-fpm$PHP_VERSION --nodaemonize