#!/bin/bash -x

DEFAULT=$(git branch|grep ^*|cut -c3-)
TAG=${1:-$DEFAULT}
VERSION=$TAG
BRANCH=$(echo $VERSION|sed 's/\.[0-9]\{8\}$//')
[ $VERSION = $BRANCH ] && VERSION="$BRANCH.x-dev"

[ $VERSION = "$DEFAULT.x-dev" ] && {
	cd $(dirname $0)
	grep self.version composer.json | while read pack version; do composer update $(echo $pack|cut -d'"' -f2); done
	git status composer.lock|grep composer.lock && {
		git stash; git pull --rebase; git stash pop
		git commit -m 'updating composer.lock with latest commit hashed for egroupware/* packages' composer.lock
		VERSION="$VERSION#$(git push|grep $DEFAULT|cut -c4-10)"
	}
}

cd $(dirname $0)

docker pull ubuntu:18.04
docker build --no-cache --build-arg "VERSION=$VERSION" -t egroupware/egroupware:$TAG . && {
	docker push egroupware/egroupware:$TAG
	# tag only stable releases as latest
	[ $TAG != "master" ] && {
		docker tag egroupware/egroupware:$TAG egroupware/egroupware:latest
		docker push egroupware/egroupware:latest
	}
	[ "$BRANCH" != $VERSION -a "${BRANCH}.x-dev" != $VERSION ] && {
		docker tag egroupware/egroupware:$VERSION egroupware/egroupware:$BRANCH
		docker push egroupware/egroupware:$BRANCH
	}
}
