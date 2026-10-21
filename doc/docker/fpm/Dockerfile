################################################################################
##
## EGroupware FPM container, extending the shared egroupware/php base-build
## image (Alpine + PHP 8.5 + extensions + Node.js/Composer, see ../base) with
## the actual EGroupware application: composer create-project + npm build.
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
	# pgsql/pdo_pgsql is NOT in the shared base-fpm image: postgresqlXX-dev pulls in a
	# surprisingly heavy transitive dependency chain (LLVM/clang, for JIT support
	# nothing here needs) that most of base-fpm's other consumers (hosting-farm,
	# mserver) don't want to pay for - only CE/EPL actually need postgres support
	&& apk add --no-cache --virtual .build-deps $PHPIZE_DEPS postgresql18-dev \
	&& apk add --no-cache libpq \
	&& docker-php-ext-install -j$(nproc) pgsql pdo_pgsql \
	&& docker-php-source delete \
	&& apk del .build-deps \
	&& COMPOSER_EXTRA=--ignore-platform-reqs \
	&& cd /usr/share \
	&& COMPOSER_ALLOW_SUPERUSER=1 composer create-project $COMPOSER_EXTRA --prefer-dist --no-scripts --no-dev egroupware/egroupware:$VERSION \
	&& composer clear-cache
RUN set -e \
	&& cd /usr/share/egroupware \
	&& npm config set fetch-retries 5 \
	&& mkdir chunks \
	&& npm install \
	# npm install fails "silent" on network timeouts, retry until it works
	&& until npm run build; do sleep 5; npm install; done \
	# fix permissions (as some npm modules have world-writable files)
	&& chmod -R o-w . \
	&& npm run css \
	# clean up and remove caches
	&& npm cache clear --force \
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
	&& sed 's/apache/www-data/' doc/rpm-build/egroupware.cron > /etc/crontabs/www-data \
	&& patch -p1 < doc/rpm-build/asyncservice.patch \
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
