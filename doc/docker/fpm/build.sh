#!/bin/bash -e
# To build PHP 8 snapshots out of master: doc/docker/fpm/build.sh 8.0 21.1.<date> master

cd $(dirname $0)

BASE_REPO=quay.io/egroupware/php
REPO=quay.io/egroupware
IMAGE=egroupware
DEFAULT_PHP_VERSION=8.5
PHP_VERSION=$DEFAULT_PHP_VERSION
# which architectures to build for multi-platform images, if buildx is available on a Docker desktop or newer Docker installation
#PLATFORMS=linux/amd64,linux/ppc64le,linux/arm/v7,linux/arm64/v8
PLATFORMS=linux/amd64,linux/arm/v7,linux/arm64/v8

if [[ $1 =~ ^[78]\.[0-9]$ ]]
then
  PHP_VERSION=$1
  shift
fi

# the one branch that should ever receive the floating ":latest" tag - never an
# older/EOL-ish branch like 23.1, even though it's built and tagged the same way
LATEST_BRANCH=26

DEFAULT=$(git branch|grep ^*|cut -c3-)
TAG=${1:-$DEFAULT}
VERSION=${2:-$TAG}
BRANCH=$(echo $VERSION|sed 's/\..*$//')
[ $BRANCH = "23" ] && BRANCH=23.1
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

docker pull $BASE_REPO:build-$PHP_VERSION

# add further tags for default PHP version only - :latest only for the actual
# current/default branch, :BRANCH (eg. :23.1) for any real (non-dev-snapshot) release
[ $PHP_VERSION = $DEFAULT_PHP_VERSION -a "$BRANCH" != $VERSION -a "dev-${BRANCH}" != $VERSION ] && {
  extra_tags="--tag $REPO/$IMAGE:$BRANCH"
  [ "$BRANCH" = "$LATEST_BRANCH" ] && extra_tags="$extra_tags --tag $REPO/$IMAGE:latest"
}

if docker buildx 2>&1 >/dev/null
then
  # buildx available --> build a multi-platform image and push it for all tags
  docker buildx build --push --platform $PLATFORMS \
    --build-arg "BASE_IMAGE=$BASE_REPO:build-$PHP_VERSION" \
    --build-arg "VERSION=$VERSION" --build-arg "PHP_VERSION=$PHP_VERSION" \
    --tag $REPO/$IMAGE:$TAG $extra_tags .
else
  # no buildx, eg. on dev only builds amd64!
  docker build \
    --build-arg "BASE_IMAGE=$BASE_REPO:build-$PHP_VERSION" \
    --build-arg "VERSION=$VERSION" --build-arg "PHP_VERSION=$PHP_VERSION" \
    --tag $REPO/$IMAGE:$TAG . && {
    docker push $REPO/$IMAGE:$TAG
    for tag in $extra_tags
    do
      [ -z "$tag" -o "$tag" = "--tags" ] || {
        docker tag $REPO/$IMAGE:$TAG $tag
        docker push $tag
      }
    done
  }
fi
