#!/bin/bash

# To update univention-appcenter-control run:
# curl https://provider-portal.software-univention.de/appcenter-selfservice/univention-appcenter-control > ~/bin/univention-appcenter-control

version=19.1
packaging=`date +%Y%m%d`
# default is now Docker!
postfix=''
project=stylite-epl

while [ $# -gt 0 ]
do
	case "$1" in
		"--packaging")
			packaging=$2
			shift; shift
			;;
		"--postfix")
			postfix=$2
			shift; shift
			;;
		"--debug")
			debug=echo
			shift
			;;
		"--help")
			echo "Usage: release-appcenter [--packaging <YYYYmmdd>] [--postfix -docker] [--debug] [--help]"
			echo "	--packaging specifiy packaging, default current date '$packaging'"
			echo "	--postfix eg. '-docker' used to find old package to copy and appended to packaging"
			echo "	--debug only echo out (modifying commands), does NOT execute them"
			exit 0
			;;
	esac
done

#echo "version=$version, packaging=$packaging, postfix=$postfix, debug=$debug"

[ ! -f ~/download/archives/egroupware-$version/egroupware-docker-$version.$packaging.tar.bz2 ] && {
	echo "No $version.$packaging packages found!"
	echo "You probably need to use --packaging <date>"
	exit 1
}

ucs=4.4

univention-appcenter-control list | tee /tmp/ucs-apps | egrep "$ucs/egroupware=$version.$packaging$postfix" || {
	copy_app=$(cat /tmp/ucs-apps | egrep "$ucs/egroupware=$version\.[0-9.]+$postfix$" | tail -1)
	[ -z "$copy_app" ] && copy_app=$ucs/egroupware
	$debug univention-appcenter-control new-version $copy_app $ucs/egroupware=$version.$packaging$postfix
}

# only use 19.1 part of changelog
sed '/egroupware-epl/q' $(dirname $0)/debian.changes | sed -e '$ d' | \
# converting changelog to html with <h3>version</h3><ul><li>...</li></ul>
sed    -e 's/egroupware-docker (/<h3>/g' \
	   -e 's/) hardy; urgency=low/<\/h3><ul>/g' \
	   -e 's/^ -- .*/<\/ul>/g' \
	   -e 's/^  \* \(.*\)/<li>\1<\/li>/g' > /tmp/README_UPDATE

$debug univention-appcenter-control upload $ucs/egroupware=$version.$packaging$postfix /tmp/README_UPDATE
