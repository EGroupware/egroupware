################################################################################
##
## EGroupware development container using Debian 12/Bookworm, NodeJS 20 and PHP from ondrej/php PPA
##
################################################################################
FROM node:20-bookworm-slim
MAINTAINER rb@egroupware.org

ARG VERSION=dev-master
ARG PHP_VERSION=8.2

# keeping build-arg in environment for entrypoint.sh
ENV VERSION=$VERSION
ENV PHP_VERSION=$PHP_VERSION

RUN set -e \
    && apt-get update \
	&& apt-get install -y apt-transport-https software-properties-common curl \
    && curl -o /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg \
    && echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list \
	&& apt-get update \
	&& bash -c "apt-get install -y php$PHP_VERSION-{cli,mysql,gd,xsl,bz2,opcache,tidy,zip,bcmath,mbstring,ldap,curl,fpm,pgsql,gmp}" \
    # there's (hopefully not yet) a php8.3 package for
    && apt-get install -y php$PHP_VERSION-apcu || true \
    && apt-get install -y php$PHP_VERSION-smbclient || true \
	# PHP 8.0 no longer has a separate json extension
    && apt-get install -y php$PHP_VERSION-json || true \
	# fpm and php.ini settings
	&& sed -e 's/^;\?listen \?=.*/listen = 9000/g' \
		-e '/allowed_clients/d' \
		-e '/pm.max_children/s/=.*/= 80/' \
		-e '/catch_workers_output/s/^;/;/' \
		-e '/error_log/d' \
		-e 's/^;\?pm.max_requests =.*/pm.max_requests = 30/' \
		-e 's/^;\?php_admin_value\[memory_limit\].*/php_admin_value[memory_limit] = 172M/' \
		-e 's/^;\?request_terminate_timeout.*/request_terminate_timeout = 70m/' \
		-i /etc/php/$PHP_VERSION/fpm/pool.d/www.conf \
	&& sed 	-e 's/^;\?session.gc_maxlifetime.*/session.gc_maxlifetime = 14400/g' \
		-e 's|^;\?date.timezone.*|date.timezone = UTC|g' \
		-e 's|^;\?sys_temp_dir.*|sys_temp_dir = /tmp|g' \
		-e 's|^;\?disable_functions.*|disable_functions = exec,passthru,shell_exec,system,proc_open,popen|g' \
		-e 's|^;\?max_execution_time \?=.*|max_execution_time = 90|g' \
		-e 's|^;\?upload_max_filesize \?=.*|upload_max_filesize = 64M|g' \
		-e 's|^;\?post_max_size \?=.*|post_max_size = 65M|g' \
		-e 's|^;\?max_input_vars \?=.*|max_input_vars = 5000|g' \
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
	# install tools to build EGroupware
	&& apt-get install -y rsync zip curl sudo cron patch \
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
	# disable certificate checks for LDAP as most LDAP and AD servers have no "valid" cert
	&& echo "TLS_REQCERT never" >> /etc/ldap/ldap.conf

# install diverse developper tools, not installed above / in stock container
RUN set -e \
    && apt-get install -y inetutils-ping iproute2 git vim yarn \
    # there's not yet a php8.3-xdebug package
    && apt-get install -y php$PHP_VERSION-cli php$PHP_VERSION-xdebug || true \
	&& echo "xdebug.mode=debug" >> /etc/php/$PHP_VERSION/fpm/conf.d/20-xdebug.ini \
	&& echo "xdebug.client_port=9001" >> /etc/php/$PHP_VERSION/fpm/conf.d/20-xdebug.ini \
	&& echo "xdebug.client_host=172.17.0.1" >> /etc/php/$PHP_VERSION/fpm/conf.d/20-xdebug.ini \
	&& ln -s /usr/local/bin/composer.phar /usr/local/bin/composer \
	&& sed 	-e 's|^;\?opcache.validate_timestamps \?=.*|opcache.validate_timestamps=1|g' \
		-i /etc/php/$PHP_VERSION/fpm/php.ini \
	&& apt-get clean \
	&& update-alternatives --install /usr/sbin/php-fpm php-fpm /usr/sbin/php-fpm$PHP_VERSION 5

VOLUME /var/www
VOLUME /var/lib/egroupware
VOLUME /var/lib/php/sessions
VOLUME /var/lib/egroupware-push

EXPOSE 9000

ADD entrypoint.sh /

CMD ["php-fpm", "--nodaemonize"]
ENTRYPOINT ["/entrypoint.sh"]