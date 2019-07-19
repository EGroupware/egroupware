#!/bin/bash -x

TAG=${1:-dev-master}
VERSION=$TAG

[ $VERSION = "dev-master" ] && {
	cd ~/egroupware
	grep self.version composer.json | while read pack version; do composer update $(echo $pack|cut -d'"' -f2); done
	git status composer.lock|grep composer.lock && {
		git stash; git pull --rebase; git stash pop
		git commit -m 'updating composer.lock with latest commit hashed for egroupware/* packages' composer.lock
		VERSION="$VERSION#$(git push|grep master|cut -c4-10)"
	}
}

cd $(dirname $0)

docker pull ubuntu:18.04
docker build --no-cache --build-arg "VERSION=$VERSION" -t egroupware/egroupware:$TAG . && {
	docker push egroupware/egroupware:$TAG
	# tag only stable releases as latest
	[ $TAG != "dev-master" ] && \
	{
		docker tag egroupware/egroupware:$TAG egroupware/egroupware:latest
		docker push egroupware/egroupware:latest
	}
	BRANCH=$(echo $VERSION|sed 's/\.[0-9]\{8\}$//')
	[ "$BRANCH" != $VERSION -a "dev-$BRANCH" != $VERSION ] && {
		docker tag egroupware/egroupware:$VERSION egroupware/egroupware:$BRANCH
		docker push egroupware/egroupware:$BRANCH
	}
	[ $VERSION != "dev-master" ] && {
		docker tag egroupware/egroupware:$VERSION egroupware/egroupware:latest
		docker push egroupware/egroupware:latest
	}
}
