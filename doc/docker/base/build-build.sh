#!/bin/bash -e
# Build and push the base-build image, extending base-fpm with Node.js, Composer
# and the shell tools needed to build EGroupware - shared by both the CE and
# development images. Called from build-fpm.sh for every new PHP point-release,
# or standalone to rebuild just this layer.

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

docker pull $REPO:fpm-$PHP_VERSION
# exact point-release (eg. 8.5.11); pass in from build-fpm.sh, or determine it ourselves if run standalone
FULL_VERSION=${1:-$(docker run --rm $REPO:fpm-$PHP_VERSION php -r 'echo PHP_VERSION;')}
[ -n "$1" ] && shift

extra_tags="--tag $REPO:build-$FULL_VERSION"
[ $PHP_VERSION = $DEFAULT_PHP_VERSION ] && extra_tags="$extra_tags --tag $REPO:build-latest"

echo -e "\nbuilding $REPO:build-$PHP_VERSION ($FULL_VERSION)\n"
docker buildx build --push --platform $PLATFORMS \
  --build-arg "BASE_IMAGE=$REPO:fpm-$PHP_VERSION" \
  --build-arg "PHP_VERSION=$PHP_VERSION" \
  --tag $REPO:build-$PHP_VERSION $extra_tags -f Dockerfile-build .

echo -e "\nbuilding dependent image: development\n"
(cd ../development; ./build.sh $PHP_VERSION)
