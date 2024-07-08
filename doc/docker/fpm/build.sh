#!/bin/bash -e
# To build PHP 8 snapshots out of master: doc/docker/fpm/build.sh 8.0 21.1.<date> master

cd $(dirname $0)

DEFAULT_PHP_VERSION=8.2
PHP_VERSION=$DEFAULT_PHP_VERSION
BASE=ubuntu:20.04
# which architectures to build for multi-platform images, if buildx is available on a Docker desktop or newer Docker installation
PLATFORMS=linux/amd64,linux/ppc64le,linux/arm/v7,linux/arm64/v8

if [[ $1 =~ ^[78]\.[0-9]$ ]]
then
  PHP_VERSION=$1
  shift
fi

DEFAULT=$(git branch|grep ^*|cut -c3-)
TAG=${1:-$DEFAULT}
VERSION=${2:-$TAG}
BRANCH=$(echo $VERSION|sed 's/\.[0-9]\{8\}$//')
[ $VERSION = $BRANCH ] && VERSION="dev-$BRANCH"

[ $VERSION = "dev-$DEFAULT" ] && (
  cd ../../..
	composer update 'egroupware/*'
	git status composer.lock|grep composer.lock && {
		git stash; git pull --rebase; git stash pop
		git commit -m 'updating composer.lock with latest commit hashed for egroupware/* packages' composer.lock
		VERSION="$VERSION#$(git push|grep $DEFAULT|cut -c4-10)"
	}
)

# add PHP_VERSION to TAG, if not the default PHP version
[ $PHP_VERSION != $DEFAULT_PHP_VERSION ] && TAG=$TAG-$PHP_VERSION

docker pull $BASE

# add further tags for default PHP version only
[ $PHP_VERSION = $DEFAULT_PHP_VERSION -a "$BRANCH" != $VERSION -a "dev-${BRANCH}" != $VERSION ] && {
  extra_tags="--tag egroupware/egroupware:latest --tag egroupware/egroupware:$BRANCH"
}

if docker buildx 2>&1 >/dev/null
then
  # buildx available --> build a multi-platform image and push it for all tags
  docker buildx build --push --platform $PLATFORMS --build-arg "VERSION=$VERSION" --build-arg "PHP_VERSION=$PHP_VERSION" --tag egroupware/egroupware:$TAG $extra_tags .
else
  # no buildx, eg. on dev only builds amd64!
  docker build --build-arg "VERSION=$VERSION" --build-arg "PHP_VERSION=$PHP_VERSION" --tag egroupware/egroupware:$TAG . && {
    docker push egroupware/egroupware:$TAG
    for tag in $extra_tags
    do
      [ -z "$tag" -o "$tag" = "--tags" ] || {
        docker tag egroupware/egroupware:$TAG $tag
        docker push $tag
      }
    done
  }
fi