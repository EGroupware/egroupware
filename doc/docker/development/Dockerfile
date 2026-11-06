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
	# pecl.php.net's REST metadata is intermittently flaky ("does not have REST info xml
	# available"), unrelated to this Dockerfile - retry rather than fail the whole build
	&& (for i in 1 2 3 4 5; do pecl install xdebug && exit 0; sleep 5; done; exit 1) \
	&& docker-php-ext-enable xdebug \
	&& docker-php-source delete \
	&& apk del .build-deps \
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
