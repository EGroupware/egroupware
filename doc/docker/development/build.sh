#!/bin/bash -x

REPO=egroupware
IMAGE=development
BASE=ubuntu:22.04
RECOMMENDED_PHP_VERSION=8.1

PHP_VERSION=${1:-8.1}

TAG=$(docker run --rm -i --entrypoint bash $REPO/$IMAGE:$PHP_VERSION -c "apt update && apt search php$PHP_VERSION-fpm" 2>/dev/null|grep php$PHP_VERSION-fpm|sed "s|^php$PHP_VERSION-fpm/focal[^ ]* .*\([78]\.[0-9]*\.[0-9]*\).*|\1|g")
test -z "$TAG" && {
	echo "Can't get new tag of $REPO/$IMAGE container --> existing"
	exit 1
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

docker pull $BASE
docker build --build-arg "VERSION=$VERSION" --build-arg="PHP_VERSION=$PHP_VERSION" -t $REPO/$IMAGE:$TAG . && {
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