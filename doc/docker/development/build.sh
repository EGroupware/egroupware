#!/bin/bash -xe

REPO=egroupware
IMAGE=development
BASE=node:20-bookworm-slim
RECOMMENDED_PHP_VERSION=8.4
PLATFORMS=linux/amd64,linux/arm64/v8

PHP_VERSION=${1:-$RECOMMENDED_PHP_VERSION}
shift

test -z "${TAG:=$1}" && {
  # php8.1-fpm/focal 8.1.18-1+ubuntu20.04.1+deb.sury.org+1 amd64 [upgradable from: 8.1.16+repack-1+ubuntu20.04.1+deb.sury.org+1]
  TAG=$(docker run --rm -i --entrypoint bash $REPO/$IMAGE -c "apt update && apt search php$PHP_VERSION-fpm" 2>/dev/null|grep php$PHP_VERSION-fpm|head -1|sed "s|^php$PHP_VERSION-fpm/[^ ]* \([78]\.[0-9]\.[0-9]*\).*|\1|g")
  # check if tag is ok
  if [[ ! $TAG =~ ^[78]\.[0-9]\.[0-9]+$ ]]
  then
    echo "Can't get new tag of $REPO/$IMAGE container --> existing"
    exit 1
  fi
}

DEFAULT=$(git branch|grep ^*|cut -c3-)
VERSION=${2:-$DEFAULT}
BRANCH=$(echo $VERSION|sed 's/\.[0-9]\{8\}$//')
[ $VERSION = $BRANCH ] && VERSION="$BRANCH.x-dev"

[ $BRANCH = "master" ] && {
	VERSION=dev-master
}
echo -e "\nbuilding $REPO/$IMAGE:$TAG\n"

cd $(dirname $0)

if docker buildx 2>&1 >/dev/null
then
  # tag master by major PHP version eg. 8.1
  [ $BRANCH = "master" ] && {
    extra_tags="--tag egroupware/development:$PHP_VERSION"
  }
  # tag only recommended PHP version as latest and $BRANCH (eg. master)
  [ $PHP_VERSION = $RECOMMENDED_PHP_VERSION ] && {
    [ $BRANCH = "master" ] && {
      extra_tags="$extra_tags --tag egroupware/development:latest"
    }
    extra_tags="$extra_tags --tag egroupware/development:$BRANCH"
  }
  # buildx available --> build a multi-platform image and push it for all tags
  docker buildx build --push --platform $PLATFORMS --no-cache --build-arg "VERSION=$VERSION" --build-arg "PHP_VERSION=$PHP_VERSION" --tag egroupware/development:$TAG $extra_tags .
else
  # no buildx, only builds amd64!
  docker pull $BASE
  docker build --no-cache --build-arg "VERSION=$VERSION" --build-arg="PHP_VERSION=$PHP_VERSION" -t $REPO/$IMAGE:$TAG . && {
    docker push $REPO/$IMAGE:$TAG
    # tag master by major PHP version eg. 8.1
    [ $BRANCH = "master" ] && {
      docker tag $REPO/$IMAGE:$TAG $REPO/$IMAGE:$PHP_VERSION
      docker push $REPO/$IMAGE:$PHP_VERSION
    }
    # tag only recommended PHP version as latest and $BRANCH (eg. master)
    [ $PHP_VERSION = $RECOMMENDED_PHP_VERSION ] && {
      [ $BRANCH = "master" ] && {
        docker tag $REPO/$IMAGE:$TAG $REPO/$IMAGE:latest
        docker push $REPO/$IMAGE:latest
      }
      docker tag $REPO/$IMAGE:$TAG $REPO/$IMAGE:$BRANCH
      docker push $REPO/$IMAGE:$BRANCH
    }
  }
fi