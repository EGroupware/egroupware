#!/bin/bash -e
# Build and push the base-php image for a given PHP version, then rebuild everything
# that extends it: base-fpm, base-build, and the development image (chained via
# build-fpm.sh -> build-build.sh). Called for every new PHP point-release - unlike
# doc/docker/fpm/build.sh (the CE image), which is only called for actual
# EGroupware releases.

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

docker pull php:$PHP_VERSION-fpm-alpine
# exact point-release (eg. 8.5.11), so images are also pinnable beyond the floating minor-version tag
FULL_VERSION=$(docker run --rm php:$PHP_VERSION-fpm-alpine php -r 'echo PHP_VERSION;')

extra_tags="--tag $REPO:base-$FULL_VERSION"
[ $PHP_VERSION = $DEFAULT_PHP_VERSION ] && extra_tags="$extra_tags --tag $REPO:base-latest"

echo -e "\nbuilding $REPO:base-$PHP_VERSION ($FULL_VERSION)\n"
docker buildx build --push --platform $PLATFORMS --build-arg "PHP_VERSION=$PHP_VERSION" --tag $REPO:base-$PHP_VERSION $extra_tags .

echo -e "\nbuilding dependent image: base-fpm\n"
./build-fpm.sh $PHP_VERSION $FULL_VERSION
