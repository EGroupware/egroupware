################################################################################
##
## EGroupware base-php image: official PHP Alpine FPM image + the light,
## universally-cheap extensions (redis/memcached/igbinary/mysqli/pdo_mysql)
## and generic security baseline, shared by every downstream image (CE,
## development, and the hosting-farm's own php-fpm/mserver images).
##
## Does NOT contain smbclient or the heavier CE/EPL-specific extensions
## (gd/xsl/bz2/zip/tidy/bcmath/ldap/pgsql/apcu/gmp) - see ../base/Dockerfile-fpm
## for those.
##
################################################################################
ARG PHP_VERSION=8.5

FROM php:${PHP_VERSION}-fpm-alpine
LABEL maintainer="rb@egroupware.org"

ARG PHP_VERSION
ENV PHP_VERSION=$PHP_VERSION

RUN set -e \
	&& apk add --no-cache --virtual .build-deps \
		$PHPIZE_DEPS \
		zlib-dev \
	&& docker-php-ext-install -j$(nproc) mysqli pdo_mysql \
	# pecl.php.net's REST metadata is intermittently flaky ("does not have REST info xml
	# available"), unrelated to this Dockerfile - retry rather than fail the whole build
	&& (for i in 1 2 3 4 5; do pecl install igbinary && exit 0; sleep 5; done; exit 1) \
	&& (for i in 1 2 3 4 5; do pecl install redis && exit 0; sleep 5; done; exit 1) \
	&& apk add --no-cache --virtual .build-deps2 libmemcached-dev cyrus-sasl-dev \
	&& apk add --no-cache libmemcached \
	&& (for i in 1 2 3 4 5; do pecl install memcached && exit 0; sleep 5; done; exit 1) \
	&& docker-php-ext-enable igbinary redis memcached \
	&& docker-php-source delete \
	&& apk del .build-deps .build-deps2 \
	# use production php.ini as starting point, like Debian/ondrej's package does
	&& cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini \
	# generic security baseline, relevant regardless of what extends this image:
	# precautionary mitigation for CVE-2026-109618/CVE-2026-109619 (two critical PHP RCEs,
	# one reportedly in the session.upload_progress handler) - nothing downstream uses it
	&& sed -e 's|^;\?session.upload_progress.enabled.*|session.upload_progress.enabled = Off|g' \
		# official mitigation for CVE-2026-109619 on PHP 8.5 (blocks the confirmed
		# fatal-backtrace path); harmless no-op on PHP versions without this directive
		-e 's|^;\?fatal_error_backtraces.*|fatal_error_backtraces = 0|g' \
		-e 's|^;\?date.timezone.*|date.timezone = UTC|g' \
		-e 's|^;\?sys_temp_dir.*|sys_temp_dir = /tmp|g' \
		-i /usr/local/etc/php/php.ini \
	# create directory for pid file
	&& mkdir -p /run/php \
	# send logs to stderr to be viewed by docker logs (php-fpm's own Alpine image
	# already defaults error_log to stderr, kept here for parity/documentation)
	&& ln -sf /dev/stderr /var/log/php$PHP_VERSION-fpm.log

EXPOSE 9000
