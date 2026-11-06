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

# Match Debian/ondrej's long-standing www-data UID/GID (33): every existing on-premise
# install's bind-mounted /var/lib/egroupware (VFS storage, header.inc.php, backups, ...)
# has files owned by that UID from years of running the previous Debian-based image -
# Alpine's own default (82) would make the entrypoint chown -R all of it on upgrade (days
# on big installs). Done first, before anything else in this image (or anything extending
# it) creates or chowns a single www-data-owned file. The Linux packages (egroupware-docker)
# use 33:33 too, a CI check compares both.
RUN sed -i 's/^www-data:x:82:82:/www-data:x:33:33:/' /etc/passwd \
	&& sed -i 's/^www-data:x:82:/www-data:x:33:/' /etc/group

RUN set -e \
	# apk upgrade: security fixes of the Alpine packages since the official php image was built (build.sh checks for upgradable packages, so a rebuild actually removes them)
	&& apk upgrade --no-cache \
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
	# activate OpenSSL's "legacy" provider (present on disk, inactive by default since the
	# 1.1->3.x provider split): persisted secrets encrypted years ago under an old OpenSSL
	# build (eg. openid/src/Keys.php's DB-password-encrypted RSA private key) can use PBE
	# schemes (eg. classic PKCS#5 v1.5 pbeWithMD5AndDES-CBC) that only the legacy provider
	# still understands; without this they fail to decrypt at all on this image
	# ("error:0308010C:digital envelope routines::unsupported"), not because the secret
	# changed, but because OpenSSL 3.x no longer loads that provider implicitly
	&& sed -e 's|^default = default_sect|default = default_sect\nlegacy = legacy_sect|' \
		-e 's|^\[default_sect\]|[default_sect]\nactivate = 1\n\n[legacy_sect]\nactivate = 1|' \
		-i /etc/ssl/openssl.cnf \
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
	&& ln -sf /dev/stderr /var/log/php$PHP_VERSION-fpm.log \
	# the official php image only has php in /usr/local/bin, but hash-bangs, cron jobs and Kubernetes commands use the usual /usr/bin/php
	&& ln -sf /usr/local/bin/php /usr/bin/php

EXPOSE 9000
