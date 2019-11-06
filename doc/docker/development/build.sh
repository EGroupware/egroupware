#!/bin/bash -x

DEFAULT=$(git branch|grep ^*|cut -c3-)
TAG=${1:-$DEFAULT}
VERSION=$TAG
BRANCH=$(echo $VERSION|sed 's/\.[0-9]\{8\}$//')
[ $VERSION = $BRANCH ] && VERSION="$BRANCH.x-dev"

cd $(dirname $0)

docker pull ubuntu:18.04
docker build --build-arg "VERSION=$VERSION" -t egroupware/development:$TAG . && {
	docker push egroupware/development:$TAG
	# tag only stable releases as latest
	[ $TAG != "master" ] && {
		docker tag egroupware/development:$TAG egroupware/development:latest
		docker push egroupware/development:latest
	}
	[ "$BRANCH" != $VERSION -a "${BRANCH}.x-dev" != $VERSION ] && {
		docker tag egroupware/development:$VERSION egroupware/development:$BRANCH
		docker push egroupware/development:$BRANCH
	}
}
