#!/bin/bash -e
# Build and push the base-fpm image, extending base-php with the heavier
# extensions (gd/xsl/bz2/zip/tidy/bcmath/ldap/apcu/gmp/smbclient) and
# the generic FPM/php.ini production hardening. Called from build.sh for every
# new PHP point-release, or standalone to rebuild just this layer.

cd $(dirname $0)

DEFAULT_PHP_VERSION=8.5
PHP_VERSION=$DEFAULT_PHP_VERSION
PLATFORMS=linux/amd64,linux/arm/v7,linux/arm64/v8

if [[ $1 =~ ^[78]\.[0-9]$ ]]
then
  PHP_VERSION=$1
  shift
fi

REPO=quay.io/egroupware/php

docker pull $REPO:base-$PHP_VERSION
# exact point-release (eg. 8.5.11); pass in from build.sh, or determine it ourselves if run standalone
FULL_VERSION=${1:-$(docker run --rm $REPO:base-$PHP_VERSION php -r 'echo PHP_VERSION;')}
[ -n "$1" ] && shift

extra_tags="--tag $REPO:fpm-$FULL_VERSION"
[ $PHP_VERSION = $DEFAULT_PHP_VERSION ] && extra_tags="$extra_tags --tag $REPO:fpm-latest"

echo -e "\nbuilding $REPO:fpm-$PHP_VERSION ($FULL_VERSION)\n"
docker buildx build --push --platform $PLATFORMS \
  --build-arg "BASE_IMAGE=$REPO:base-$PHP_VERSION" \
  --build-arg "PHP_VERSION=$PHP_VERSION" \
  --tag $REPO:fpm-$PHP_VERSION $extra_tags -f Dockerfile-fpm .

echo -e "\nbuilding dependent image: base-build\n"
./build-build.sh $PHP_VERSION $FULL_VERSION
