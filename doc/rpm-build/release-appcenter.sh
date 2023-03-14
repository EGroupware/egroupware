#!/bin/bash

# To update univention-appcenter-control run:
# curl https://provider-portal.software-univention.de/appcenter-selfservice/univention-appcenter-control > ~/bin/univention-appcenter-control

version=23.1
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

# replace images so Univention can automatic fetch them into their repo
#uni_version=$(curl https://appcenter-test.software-univention.de/univention-repository/4.4/maintained/component/ 2>/dev/null | grep egroupware_$packaging | sed 's|.*href="\(egroupware_[0-9]*\)/".*|\1|')
#curl https://appcenter-test.software-univention.de/univention-repository/4.4/maintained/component/$uni_version/compose 2>/dev/null > /tmp/compose
univention-appcenter-control get 4.4/egroupware=$version.$packaging$postfix --json | jq -r .compose > /tmp/compose
sed -i "" \
	-e "s|image:.*docker.software-univention.de/egroupware-egroupware.*|image: download.egroupware.org/egroupware/epl:$version.$packaging|" \
	-e "s|image:.*docker.software-univention.de/egroupware-push.*|image: phpswoole/swoole:5.0-php8.1-alpine|" \
	-e "s|image:.*docker.software-univention.de/egroupware-nginx.*|image: nginx:stable-alpine|" \
	/tmp/compose
$debug univention-appcenter-control upload $ucs/egroupware=$version.$packaging$postfix /tmp/compose
