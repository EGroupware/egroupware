################################################################################
##
## EGroupware development container, extending the shared egroupware/php
## base-build image (Alpine + PHP 8.5 + extensions + Node.js/Composer, see
## ../base) with xdebug and other developer tools. Does NOT bundle app
## source - mount your own checkout at /var/www.
##
################################################################################
ARG BASE_IMAGE=quay.io/egroupware/php:build-latest

FROM ${BASE_IMAGE}

COPY --from=ghcr.io/php/pie:1.5.5-bin /pie /usr/bin/pie
LABEL maintainer="rb@egroupware.org"

ARG VERSION=dev-master
ARG PHP_VERSION=8.5
ENV VERSION=$VERSION
ENV PHP_VERSION=$PHP_VERSION

RUN set -e \
	# pgsql/pdo_pgsql is NOT in the shared base-fpm image - see ../fpm/Dockerfile
	# for why (postgresqlXX-dev pulls in a heavy LLVM/clang dependency chain)
	&& apk add --no-cache --virtual .build-deps $PHPIZE_DEPS postgresql18-dev linux-headers \
	&& apk add --no-cache libpq \
	&& docker-php-ext-install -j$(nproc) pgsql pdo_pgsql \
	# xdebug with PIE (replacement of pecl, not in the official php images from 8.6 on), only used while building; PHP 8.6 has no stable xdebug yet,
	# the image then has no debugger. packagist is intermittently flaky - retry rather than fail the whole build
	&& case "$PHP_VERSION" in \
		8.[6-9]*|9.*) echo "PHP $PHP_VERSION: xdebug is not installed (no support yet)"; : > /usr/local/etc/php/conf.d/20-xdebug.ini ;; \
		*) apk add --no-cache --virtual .build-deps2 git unzip libtool \
			&& ok=; for i in 1 2 3 4 5; do pie install xdebug/xdebug && ok=1 && break; sleep 5; done; [ -n "$ok" ] || exit 1 ;; \
	esac \
	&& rm -rf /usr/bin/pie /root/.pie /root/.cache /root/.config \
	&& docker-php-source delete \
	&& apk del .build-deps \
	&& (apk del .build-deps2 2>/dev/null || true) \
	&& echo "xdebug.mode=debug" >> /usr/local/etc/php/conf.d/20-xdebug.ini \
	&& echo "xdebug.client_port=9001" >> /usr/local/etc/php/conf.d/20-xdebug.ini \
	&& echo "xdebug.client_host=172.17.0.1" >> /usr/local/etc/php/conf.d/20-xdebug.ini \
	# dev wants live code reload, not base-fpm's aggressive production caching
	&& sed -e 's|^;\?opcache.validate_timestamps\s*=.*|opcache.validate_timestamps=1|g' \
		-i /usr/local/etc/php/php.ini \
	&& apk add --no-cache vim yarn git iproute2

VOLUME /var/www
VOLUME /var/lib/egroupware
VOLUME /var/lib/php/sessions
VOLUME /var/lib/egroupware-push

EXPOSE 9000

ADD entrypoint.sh /

CMD ["php-fpm", "--nodaemonize"]
ENTRYPOINT ["/entrypoint.sh"]
