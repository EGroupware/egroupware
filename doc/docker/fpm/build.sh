#!/bin/bash -x

cd $(dirname $0)

DEFAULT_PHP_VERSION=7.4
PHP_VERSION=$DEFAULT_PHP_VERSION

if [[ $1 =~ ^[78]\.[0-9]$ ]]
then
  PHP_VERSION=$1
  shift
fi

DEFAULT=$(git branch|grep ^*|cut -c3-)
TAG=${1:-$DEFAULT}
VERSION=$TAG
BRANCH=$(echo $VERSION|sed 's/\.[0-9]\{8\}$//')
[ $VERSION = $BRANCH ] && VERSION="$BRANCH.x-dev"

[ $VERSION = "$DEFAULT.x-dev" ] && {
	grep self.version composer.json | while read pack version; do composer update $(echo $pack|cut -d'"' -f2); done
	git status composer.lock|grep composer.lock && {
		git stash; git pull --rebase; git stash pop
		git commit -m 'updating composer.lock with latest commit hashed for egroupware/* packages' composer.lock
		VERSION="$VERSION#$(git push|grep $DEFAULT|cut -c4-10)"
	}
}

# add PHP_VERSION to TAG, if not the default PHP version
[ $PHP_VERSION != $DEFAULT_PHP_VERSION ] && TAG=$TAG-$PHP_VERSION

docker pull ubuntu:20.04
docker build --no-cache --build-arg "VERSION=$VERSION" --build-arg "PHP_VERSION=$PHP_VERSION" -t egroupware/egroupware:$TAG . && {
	docker push egroupware/egroupware:$TAG
	# further tags are only for the default PHP version
	[ $PHP_VERSION != $DEFAULT_PHP_VERSION ] && exit
	# tag only stable releases as latest
	[ $TAG != "master" ] && {
	  docker tag egroupware/egroupware:$TAG egroupware/egroupware:latest
		docker push egroupware/egroupware:latest
	}
	[ "$BRANCH" != $VERSION -a "${BRANCH}.x-dev" != $VERSION ] && {
		docker tag egroupware/egroupware:$TAG egroupware/egroupware:$BRANCH
		docker push egroupware/egroupware:$BRANCH
	}
}
