#!/bin/bash -e
# To build PHP 8 snapshots out of master: doc/docker/fpm/build.sh 8.0 21.1.<date> master

cd $(dirname $0)

BASE_REPO=quay.io/egroupware/php
REPO=quay.io/egroupware
IMAGE=egroupware
# keep Docker Hub in sync too - stylite/docker/Dockerfile and doc/docker/docker-compose.yml
# still pull the bare (docker.io) name, not quay.io
DOCKERHUB_IMAGE=egroupware/egroupware
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

# mirror the same tags to Docker Hub (egroupware/egroupware) - stylite/docker/Dockerfile and
# doc/docker/docker-compose.yml still pull the bare (docker.io) name. This is a registry-to-
# registry copy (imagetools, no rebuild), but still re-uploads every layer from wherever it
# runs, so run it on dev.egroupware.org (better upload bandwidth than here) and in the
# background - it does not block the EPL image build that layers on top of the quay.io tag
hub_tags=$(echo "$extra_tags" | sed "s#$REPO/$IMAGE#$DOCKERHUB_IMAGE#g")
ssh dev "nohup docker buildx imagetools create --tag $DOCKERHUB_IMAGE:$TAG $hub_tags $REPO/$IMAGE:$TAG > /tmp/dockerhub-sync-$TAG.log 2>&1 &" \
  && echo "Docker Hub sync for $TAG started in the background on dev (log: dev:/tmp/dockerhub-sync-$TAG.log)" \
  || echo "WARNING: could not start the Docker Hub sync on dev" >&2

# a new release of the current branch (what :26 and :latest point to) is tested by the install tests of the packages in EGroupware/build.opensuse.org:
# they install the just published image with the egroupware-docker package on the supported distros, a failure is visible in the Actions tab there.
# Image-only releases do not touch that repository, so the (manual) workflow is triggered here, never fails the build, set NO_TESTS=1 to skip it
[ -z "$NO_TESTS" -a -n "$extra_tags" -a "$BRANCH" = "$LATEST_BRANCH" ] && {
  if command -v gh >/dev/null
  then
    gh workflow run package-tests.yml --repo EGroupware/build.opensuse.org --ref master -f distro=default -f test=all \
      && echo "Started the package install tests: https://github.com/EGroupware/build.opensuse.org/actions/workflows/package-tests.yml" \
      || echo "WARNING: could not start the package install tests (gh auth login?)" >&2
  else
    echo "WARNING: gh not found, package install tests NOT started" >&2
  fi
}
true
