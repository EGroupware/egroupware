#!/bin/bash -x
# To build PHP 8 snapshots out of master: doc/docker/fpm/build.sh 8.0 21.1.<date> master

cd $(dirname $0)

DEFAULT_PHP_VERSION=8.1
PHP_VERSION=$DEFAULT_PHP_VERSION

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

docker pull ubuntu:20.04
docker build --no-cache --build-arg "VERSION=$VERSION" --build-arg "PHP_VERSION=$PHP_VERSION" -t egroupware/egroupware:$TAG . && {
	docker push egroupware/egroupware:$TAG
	# further tags are only for the default PHP version
	[ $PHP_VERSION != $DEFAULT_PHP_VERSION ] && {
		docker tag egroupware/egroupware:$TAG egroupware/egroupware:$BRANCH-$PHP_VERSION
		docker push egroupware/egroupware:$BRANCH-$PHP_VERSION
	  exit
	}
	# tag only stable releases as latest
	[ $TAG != "master" ] && {
		docker tag egroupware/egroupware:$TAG egroupware/egroupware:latest
		docker push egroupware/egroupware:latest
	}
	[ "$BRANCH" != $VERSION -a "dev-${BRANCH}" != $VERSION ] && {
		docker tag egroupware/egroupware:$TAG egroupware/egroupware:$BRANCH
		docker push egroupware/egroupware:$BRANCH
	}
}
