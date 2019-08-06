################################################################################
##
## EGroupware FPM container using Ubuntu 18.04 and PHP 7.3 from ondrej/php PPA
##
################################################################################
FROM ubuntu:18.04
MAINTAINER rb@egroupware.org

ARG VERSION=19.1.x-dev

RUN apt-get update \
	&& apt-get install -y software-properties-common \
	&& LC_ALL=C.UTF-8 add-apt-repository -y ppa:ondrej/php \
	&& apt-get update \
	&& bash -c "apt-get install -y php7.3-{cli,mysql,json,gd,xsl,bz2,opcache,apcu,tidy,zip,bcmath,mbstring,smbclient,ldap,curl,fpm,pgsql}" \
	# fpm and php.ini settings
	&& sed -e 's/^;\?listen \?=.*/listen = 9000/g' \
		-e '/allowed_clients/d' \
		-e '/pm.max_children/s/=.*/= 80/' \
		-e '/catch_workers_output/s/^;/;/' \
		-e '/error_log/d' \
		-e 's/^;\?pm.max_requests =.*/pm.max_requests = 30/' \
		-e 's/^;\?php_admin_value\[memory_limit\].*/php_admin_value[memory_limit] = 172M/' \
		-e 's/^;\?request_terminate_timeout.*/request_terminate_timeout = 70m/' \
		-i /etc/php/7.3/fpm/pool.d/www.conf \
	&& sed 	-e 's/^;\?session.gc_maxlifetime.*/session.gc_maxlifetime = 14400/g' \
		-e 's|^;\?date.timezone.*|date.timezone = UTC|g' \
		-e 's|^;\?sys_temp_dir.*|sys_temp_dir = /tmp|g' \
		-e 's|^;\?disable_functions.*|disable_functions = exec,passthru,shell_exec,system,proc_open,popen|g' \
		-e 's|^;\?max_execution_time \?=.*|max_execution_time = 90|g' \
		-e 's|^;\?upload_max_filesize \?=.*|upload_max_filesize = 64M|g' \
		-e 's|^;\?post_max_size \?=.*|post_max_size = 65M|g' \
		-e 's|^;\?max_input_vars \?=.*|max_input_vars = 2000|g' \
		-e 's|^;\?zlib.output_compression \?=.*|zlib.output_compression = On|g' \
		-e 's|^;\?opcache.validate_timestamps \?=.*|opcache.validate_timestamps=0|g' \
		-i /etc/php/7.3/fpm/php.ini \
	&& sed 	-e 's|^;\?date.timezone.*|date.timezone = UTC|g' \
		-e 's|^;\?sys_temp_dir.*|sys_temp_dir = /tmp|g' \
		-i /etc/php/7.3/cli/php.ini \
	&& mkdir -p /run/php \
	# send logs to stderr to be viewed by docker logs
	&& ln -s /dev/stderr /var/log/php7.3-fpm.log \
	# install tools to build EGroupware
	&& apt-get install -y rsync npm zip curl sudo cron patch \
	&& npm install -g grunt-cli \
	&& bash -c \
'EXPECTED_SIGNATURE=$(curl https://composer.github.io/installer.sig); \
curl https://getcomposer.org/installer > composer-setup.php; \
ACTUAL_SIGNATURE=$(php -r "echo hash_file(\"sha384\", \"composer-setup.php\");"); \
if [ "$EXPECTED_SIGNATURE" != "$ACTUAL_SIGNATURE" ]; \
then \
    >&2 echo "ERROR: Invalid Composer installer signature"; \
    RESULT=1; \
else \
	php composer-setup.php --quiet --install-dir /usr/local/bin; \
	RESULT=$?; \
fi; \
rm composer-setup.php; \
exit $RESULT' \
	# build EGroupware
	&& cd /usr/share \
	&& composer.phar create-project --prefer-dist --no-scripts egroupware/egroupware:$VERSION \
	&& cd egroupware \
	&& npm install \
	&& grunt \
	# clean up and remove caches
	&& composer.phar clear-cache \
	&& rm -f /usr/local/bin/composer.phar \
	&& npm uninstall -g grunt-cli \
	&& npm cache clear \
	&& apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false npm zip \
	&& apt-get clean \
	# create data directory
	&& mkdir -p /var/lib/egroupware/default/files/sqlfs \
	&& mkdir -p /var/lib/egroupware/default/backup \
	&& chown -R www-data:www-data /var/lib/egroupware \
	&& chmod 700 /var/lib/egroupware/ \
	&& ln -s /var/lib/egroupware/header.inc.php /usr/share/egroupware \
	# install cron-job and disable fallback async service and ability to switch them off in admin
	&& sed 's/apache/www-data/' doc/rpm-build/egroupware.cron > /etc/cron.d/egroupware \
	&& patch -p1 < doc/rpm-build/asyncservice.patch \
	# disable certificate checks for LDAP as most LDAP and AD servers have no "valid" cert
	&& echo "TLS_REQCERT never" >> /etc/ldap/ldap.conf \
	# mv sources to a different directory so entrypoint can rsync them to volumn for both nginx and fpm
	&& mv /usr/share/egroupware /usr/share/egroupware-sources

VOLUME /usr/share/egroupware
VOLUME /var/lib/egroupware

EXPOSE 9000

ADD entrypoint.sh /

CMD ["php-fpm7.3", "--nodaemonize"]
ENTRYPOINT ["/entrypoint.sh"]
