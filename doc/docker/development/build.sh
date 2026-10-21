#!/bin/bash -xe

REPO=egroupware
IMAGE=development
BASE_REPO=quay.io/egroupware/php
DEFAULT_PHP_VERSION=8.5
PHP_VERSION=$DEFAULT_PHP_VERSION
PLATFORMS=linux/amd64,linux/arm/v7,linux/arm64/v8

if [[ $1 =~ ^[78]\.[0-9]$ ]]
then
  PHP_VERSION=$1
  shift
fi

cd $(dirname $0)

docker pull $BASE_REPO:build-$PHP_VERSION
# exact point-release (eg. 8.5.11); pass in from build-build.sh, or determine it ourselves if run standalone
FULL_VERSION=${1:-$(docker run --rm $BASE_REPO:build-$PHP_VERSION php -r 'echo PHP_VERSION;')}
[ -n "$1" ] && shift

DEFAULT=$(git branch|grep ^*|cut -c3-)
VERSION=${2:-$DEFAULT}
BRANCH=$(echo $VERSION|sed 's/\.[0-9]\{8\}$//')
[ $VERSION = $BRANCH ] && VERSION="$BRANCH.x-dev"
[ $BRANCH = "master" ] && VERSION=dev-master

echo -e "\nbuilding $REPO/$IMAGE:$PHP_VERSION ($FULL_VERSION)\n"

docker buildx build --push --platform $PLATFORMS \
  --build-arg "BASE_IMAGE=$BASE_REPO:build-$PHP_VERSION" \
  --build-arg "VERSION=$VERSION" \
  --build-arg "PHP_VERSION=$PHP_VERSION" \
  --tag $REPO/$IMAGE:$PHP_VERSION \
  --tag $REPO/$IMAGE:$FULL_VERSION \
  --tag $REPO/$IMAGE:latest \
  --tag $REPO/$IMAGE:$BRANCH \
  .
