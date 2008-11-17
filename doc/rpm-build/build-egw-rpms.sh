#!/bin/bash -x
# This script work for generating rpms without Root rights
# When you create rmp's with Root rights and you have as example
# the follow command rm -rf / in your script you are in trouble :-)
#
# Change the path names for ANONCVSDIR and RHBASE to you needs.
#
# When you would create daily rpm's with update from CVS include
# delete the # sign at the start from the follow lines
#
# cd $ANONCVSDIR
# cvs update -Pd
# This scipt create auotmaticly signed rpm's
# When you don't want signed rpm's change the follow line from
#
# rpmbuild -bb --sign egroupware-rh.spec             >> $LOGFILE 2>&1
#
# to
# rpmbuild -bb egroupware-rh.spec                    >> $LOGFILE 2>&1
#
# in the script
# How to create GPG keys to sign your rpm's you will found in a seperate
# Document
#
# Script changed 2004 May 21 Reiner Jung
# Script changed 2005 Apr 15 by Ralf Becker and Wim Bonis
# 2005 Sep 20 Ralf Becker: disabled fedora 2 build

#SVNURL="http://svn.egroupware.org/egroupware/branches/1.6/"
#SVNURL="http://svn.egroupware.org/egroupware/tags/1.6pre1/"
SVNURL="http://svn.egroupware.org/egroupware/trunk/"

SPECFILE="egroupware-1.6.spec"
SOURCEFILES="egroupware_fedora.tar.bz2 egroupware_suse.tar.bz2 manageheader.php.patch class.uiasyncservice.inc.php.patch"

CONTRIB="gallery icalsrv mydms"
EXTRAPACKAGES="egw-pear $CONTRIB"
for p in $EXTRAPACKAGES
do
   EXCLUDE_CONTRIB="$EXCLUDE_CONTRIB --exclude=egroupware/$p"
   ONLY_CONTRIB="$ONLY_CONTRIB egroupware/$p"
done

PACKAGENAME=`grep "%define packagename" $SPECFILE | cut -f3 -d' '`
VERSION=`grep "%define egwversion" $SPECFILE | cut -f3 -d' '`
PACKAGING=`grep "%define packaging" $SPECFILE | cut -f3 -d' '`

HOMEBUILDDIR=`whoami`
ANONCVSDIR=/tmp/build_root/egw_buildroot-svn
NOSVNDIR=/tmp/build_root/egw_buildroot
RHBASE=$HOME/rpm
SRCDIR=$RHBASE/SOURCES
SPECDIR=$RHBASE/SPECS
LOGFILE=$RHBASE/LOG/build-$PACKAGENAME-$VERSION.$PACKAGING.log
VIRUSSCAN=$SPECDIR/clamav_scan-$PACKAGENAME-$VERSION.$PACKAGING.log
MD5SUM=$SRCDIR/md5sum-$PACKAGENAME-$VERSION.$PACKAGING.txt

mkdir -p $RHBASE/SOURCES $RHBASE/SPECS $RHBASE/BUILD $RHBASE/SRPMS $RHBASE/RPMS $RHBASE/LOG $ANONCVSDIR $NOSVNDIR

cp *.spec $RHBASE/SPECS/
cp $SOURCEFILES $RHBASE/SOURCES/
echo "Starting build process of - $PACKAGENAME $VERSION"                     		>  $LOGFILE
echo ""											>> $LOGFILE 2>&1
date                                                        				>> $LOGFILE 2>&1
cd $ANONCVSDIR

if [ ! -d egroupware/phpgwapi ]	# new checkout
then
	if [ -z "$SVNREVISION" ]; then
		echo -n "Creating a new checkout ... "					>> $LOGFILE 2>&1
		svn checkout $SVNURL"aliases/default" ./
	else
		echo -n "Creating a new checkout for revision $SVNREVISION ... "	>> $LOGFILE 2>&1
		svn checkout -r $SVNREVISION $SVNURL"aliases/default" ./
	fi

	cd egroupware
	for CONTRIBMODULE in $CONTRIB; do
		if [ -z "$CONTRIB_SVNREVISION" ]; then
			svn checkout $SVNURL"$CONTRIBMODULE"
		else
			svn checkout -r $SVNREVISION $SVNURL"$CONTRIBMODULE"
		fi
	done
else
	[ -z "$SVNREVISION" ] && SVNREVISION=HEAD
	echo -n "Updating existing checkout ... "					>> $LOGFILE 2>&1
	cd egroupware
	svn update -r $SVNREVISION . *
fi

cd $ANONCVSDIR

echo "done"										>> $LOGFILE 2>&1

echo -n "Change directory rights back ... "		>> $LOGFILE 2>&1
chmod -R u=rwX,g=rX,o=rX .						>> $LOGFILE 2>&1
chmod +x egroupware/*/*cli.php egroupware/phpgwapi/cron/*.php	>> $LOGFILE 2>&1
echo "done"										>> $LOGFILE 2>&1

echo -n "Starting anti virus scan ... "							>> $LOGFILE 2>&1
test -x /usr/bin/clamscan && /usr/bin/clamscan --quiet -r $ANONCVSDIR --log=$VIRUSSCAN
echo "done"										>> $LOGFILE 2>&1

rm -rf $NOSVNDIR/egroupware
cp -ra $ANONCVSDIR/egroupware $NOSVNDIR
find $NOSVNDIR -name .svn | xargs rm -rf

cd $NOSVNDIR
echo -n "building tar.gz ... "								>> $LOGFILE 2>&1
tar --owner=root --group=root -czf $SRCDIR/$PACKAGENAME-$VERSION.$PACKAGING.tar.gz $EXCLUDE_CONTRIB egroupware  	2>&1 | tee -a $LOGFILE
for CONTRIBMODULE in $EXTRAPACKAGES; do
	tar --owner=root --group=root -czf $SRCDIR/$PACKAGENAME-$CONTRIBMODULE-$VERSION.$PACKAGING.tar.gz egroupware/$CONTRIBMODULE 		>> $LOGFILE 2>&1
done
echo "done"										>> $LOGFILE 2>&1

echo -n "building tar.bz2 ... "						     		>> $LOGFILE 2>&1
tar --owner=root --group=root -cjf $SRCDIR/$PACKAGENAME-$VERSION.$PACKAGING.tar.bz2 $EXCLUDE_CONTRIB egroupware	>> $LOGFILE 2>&1
for CONTRIBMODULE in $EXTRAPACKAGES; do
	tar --owner=root --group=root -cjf $SRCDIR/$PACKAGENAME-$CONTRIBMODULE-$VERSION.$PACKAGING.tar.bz2 egroupware/$CONTRIBMODULE 		>> $LOGFILE 2>&1
done
echo "done"										>> $LOGFILE 2>&1

echo -n "building zip ... "								>> $LOGFILE 2>&1
for CONTRIBMODULE in $EXTRAPACKAGES; do
	zip -q -r -9 $SRCDIR/$PACKAGENAME-$CONTRIBMODULE-$VERSION.$PACKAGING.zip egroupware/$CONTRIBMODULE 	>> $LOGFILE 2>&1
done
rm -rf $ONLY_CONTRIB >> $LOGFILE 2>&1
zip -q -r -9 $SRCDIR/$PACKAGENAME-$VERSION.$PACKAGING.zip egroupware >> $LOGFILE 2>&1
echo "done"										>> $LOGFILE 2>&1

echo "Building tar.gz, tar.bz and zip archives finnished"				>> $LOGFILE 2>&1

echo "------------------------------------------"              				>> $LOGFILE 2>&1
echo "Create the md5sum file for tar.gz, tar.bz, zip ($MD5SUM)"				>> $LOGFILE 2>&1
echo "------------------------------------------"              				>> $LOGFILE 2>&1

cd $SRCDIR
for f in eGroupware*-$VERSION.$PACKAGING.*
do
	md5sum $f >> $MD5SUM 2>&1
done

echo "sign the md5sum file"													>> $LOGFILE 2>&1
rm -f $MD5SUM.asc															>> $LOGFILE 2>&1
gpg --local-user packager@egroupware.org --clearsign $MD5SUM				>> $LOGFILE 2>&1

echo "delete the original md5sum file"										>> $LOGFILE 2>&1
rm -rf $MD5SUM			  	 												>> $LOGFILE 2>&1

echo "------------------------------------------"              				>> $LOGFILE 2>&1
echo "End Create md5sum of tar.gz, tar.bz, zip"              				>> $LOGFILE 2>&1
echo "------------------------------------------"              				>> $LOGFILE 2>&1

echo "Building of $PACKAGENAME $VERSION finnished"							>> $LOGFILE 2>&1
