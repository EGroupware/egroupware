################################################################################
##
## EGroupware FPM container using Ubuntu 18.04 and PHP 7.3 from ondrej/php PPA
##
################################################################################
FROM ubuntu:20.04
MAINTAINER rb@egroupware.org

ARG VERSION=dev-master
ARG PHP_VERSION=8.1

# keeping build-arg in environment for entrypoint.sh
ENV VERSION=$VERSION
ENV PHP_VERSION=$PHP_VERSION

RUN apt-get update \
	&& apt-get install -y software-properties-common \
	&& LC_ALL=C.UTF-8 add-apt-repository -y ppa:ondrej/php \
	&& apt-get update \
	&& bash -c "apt-get install -y php$PHP_VERSION-{cli,mysql,gd,xsl,bz2,opcache,tidy,zip,bcmath,mbstring,smbclient,ldap,curl,fpm,pgsql,gmp,memcached}" \
	# php8.{0,1}-apcu recommends (not needed) php-apcu-bc, which cause PHP 7.4 to be installed :(
    && apt-get install --no-install-recommends -y php$PHP_VERSION-apcu \
	&& bash -c "[[ $PHP_VERSION =~ ^8\..* ]] || apt-get install -y php$PHP_VERSION-json" \
	# fpm and php.ini settings
	&& sed -e 's/^;\?listen \?=.*/listen = 9000/g' \
		-e '/allowed_clients/d' \
		-e '/pm.max_children/s/=.*/= 80/' \
		-e '/pm.start_servers/s/=.*/= 10/' \
		-e '/pm.min_spare_servers/s/=.*/= 10/' \
		-e '/pm.max_spare_servers/s/=.*/= 20/' \
		-e '/catch_workers_output/s/^;/;/' \
		-e '/error_log/d' \
		-e 's/^;\?pm.max_requests =.*/pm.max_requests = 30/' \
		-e 's/^;\?php_admin_value\[memory_limit\].*/php_admin_value[memory_limit] = 172M/' \
		-e 's/^;\?request_terminate_timeout.*/request_terminate_timeout = 70m/' \
		-i /etc/php/$PHP_VERSION/fpm/pool.d/www.conf \
	&& sed 	-e 's/^;\?session.gc_maxlifetime.*/session.gc_maxlifetime=14400/g' \
		-e 's|^;\?date.timezone.*|date.timezone = UTC|g' \
		-e 's|^;\?sys_temp_dir.*|sys_temp_dir = /tmp|g' \
		-e 's|^;\?disable_functions.*|disable_functions = exec,passthru,shell_exec,system,proc_open,popen|g' \
		-e 's|^;\?max_execution_time \?=.*|max_execution_time = 90|g' \
		-e 's|^;\?upload_max_filesize \?=.*|upload_max_filesize = 64M|g' \
		-e 's|^;\?post_max_size \?=.*|post_max_size = 65M|g' \
		-e 's|^;\?max_input_vars \?=.*|max_input_vars = 2000|g' \
		-e 's|^;\?zlib.output_compression \?=.*|zlib.output_compression = On|g' \
		-e 's|^;\?opcache.validate_timestamps \?=.*|opcache.validate_timestamps=0|g' \
		-i /etc/php/$PHP_VERSION/fpm/php.ini \
	&& sed 	-e 's|^;\?date.timezone.*|date.timezone = UTC|g' \
		-e 's|^;\?sys_temp_dir.*|sys_temp_dir = /tmp|g' \
		-i /etc/php/$PHP_VERSION/cli/php.ini \
	# create directory for pid file
	&& mkdir -p /run/php \
	# send logs to stderr to be viewed by docker logs
	&& ln -s /dev/stderr /var/log/php$PHP_VERSION-fpm.log \
	&& update-alternatives --install /usr/sbin/php-fpm php-fpm /usr/sbin/php-fpm$PHP_VERSION 5 \
	# install tools to build EGroupware
	&& apt-get install -y rsync zip curl sudo cron patch \
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
	&& cd /usr/share \
    # not all dependencies already allow PHP 8.x, thought what we use from them works
    && bash -c "[[ $PHP_VERSION  =~ ^8\..* ]]" && COMPOSER_EXTRA=--ignore-platform-reqs || true \
	&& composer.phar create-project $COMPOSER_EXTRA --prefer-dist --no-scripts --no-dev egroupware/egroupware:$VERSION \
	# clean up and remove caches
	&& composer.phar clear-cache \
	&& rm -f /usr/local/bin/composer.phar \
	&& cd /usr/share/egroupware \
    # install nodejs 16.x PPA (Shoelace requires >= 14.17, Ubuntu 22.04 only has 12.x)
    && curl -fsSL https://deb.nodesource.com/setup_16.x | bash - \
    && apt-get install -y nodejs \
	&& npm install -g grunt-cli \
	&& npm install \
	&& grunt \
    && mkdir chunks && npm run build \
	# clean up and remove caches
	&& npm uninstall -g grunt-cli \
	&& npm cache clear --force \
	&& apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false zip \
	&& apt-get clean \
	# create data directory
	&& mkdir -p /var/lib/egroupware/default/files/sqlfs \
	&& mkdir -p /var/lib/egroupware/default/backup \
	&& chown -R www-data:www-data /var/lib/egroupware \
	&& chmod 700 /var/lib/egroupware/ \
	&& ln -s /var/lib/egroupware/header.inc.php /usr/share/egroupware \
	# create directory for push config
	&& mkdir -p /var/lib/egroupware-push \
    && chown -R www-data:www-data /var/lib/egroupware-push \
    && ln -s /var/lib/egroupware-push/config.inc.php /usr/share/egroupware/swoolepush \
	# install cron-job and disable fallback async service and ability to switch them off in admin
	&& sed 's/apache/www-data/' doc/rpm-build/egroupware.cron > /etc/cron.d/egroupware \
	&& patch -p1 < doc/rpm-build/asyncservice.patch \
	# disable certificate checks for LDAP as most LDAP and AD servers have no "valid" cert
	&& ls /etc/ldap/ldap.conf >/dev/null 2>&1 || apt-get install -y libldap-common \
    && echo "TLS_REQCERT never" >> /etc/ldap/ldap.conf \
	# mv sources to a different directory so entrypoint can rsync them to volume for both nginx and fpm
	&& mv /usr/share/egroupware /usr/share/egroupware-sources

VOLUME /usr/share/egroupware
VOLUME /var/lib/egroupware
VOLUME /var/lib/php/sessions
VOLUME /var/lib/egroupware-push

EXPOSE 9000

ADD entrypoint.sh /

CMD ["php-fpm", "--nodaemonize"]
ENTRYPOINT ["/entrypoint.sh"]