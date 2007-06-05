#! /bin/bash
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

SVNURL="http://svn.egroupware.org/egroupware/branches/1.4/"
# 1.4 BETA 1
#SVNREVISION="23355"
# 1.4 BETA 2
#SVNREVISION="23439"
# 1.4 BETA 3
#SVNREVISION="23465"
# 1.4 BETA 5
#SVNREVISION="23743"
# 1.4.001 final
#SVNREVISION="24012"

SPECFILE="egroupware-1.4.spec"
SOURCEFILES="egroupware_fedora.tar.bz2 egroupware_suse.tar.bz2 manageheader.php.patch class.uiasyncservice.inc.php.patch"

#CONTRIB="jinn workflow messenger egwical icalsrv gallery"
CONTRIB="icalsrv gallery"
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
	
#[ "$CVSACCOUNT" = 'pserver:anonymous' ] && CVS_RSH="ssh" cvs -d:$CVSACCOUNT@cvs.sourceforge.net:/cvsroot/egroupware login

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
else											# updating an existing checkout in the build-root
	echo -n "Updating existing checkout ... "					>> $LOGFILE 2>&1
	svn update -r HEAD
fi

cd $ANONCVSDIR

echo "done"										>> $LOGFILE 2>&1

echo -n "Change directory rights back ... "						>> $LOGFILE 2>&1
find . -type d -exec chmod 775 {} \;
find . -type f -exec chmod 644 {} \;
echo "done"										>> $LOGFILE 2>&1

echo -n "Starting anti virus scan ... "							>> $LOGFILE 2>&1
#test -x /usr/bin/clamscan && /usr/bin/clamscan --quiet -r $ANONCVSDIR --log=$VIRUSSCAN
echo "done"										>> $LOGFILE 2>&1

rm -rf $NOSVNDIR/egroupware
cp -ra $ANONCVSDIR/egroupware $NOSVNDIR
find $NOSVNDIR -name .svn | xargs rm -rf

cd $ANONCVSDIR
echo -n "building tar.gz ... "								>> $LOGFILE 2>&1
tar czf $SRCDIR/$PACKAGENAME-$VERSION.$PACKAGING-svn.tar.gz $EXCLUDE_CONTRIB egroupware  	2>&1 | tee -a $LOGFILE
for CONTRIBMODULE in $EXTRAPACKAGES; do
	tar czf $SRCDIR/$PACKAGENAME-$CONTRIBMODULE-$VERSION.$PACKAGING-svn.tar.gz egroupware/$CONTRIBMODULE 		>> $LOGFILE 2>&1
done
echo "done"										>> $LOGFILE 2>&1

echo -n "building tar.bz2 ... "						     		>> $LOGFILE 2>&1
tar cjf $SRCDIR/$PACKAGENAME-$VERSION.$PACKAGING-svn.tar.bz2 $EXCLUDE_CONTRIB egroupware	>> $LOGFILE 2>&1
for CONTRIBMODULE in $EXTRAPACKAGES; do
	tar cjf $SRCDIR/$PACKAGENAME-$CONTRIBMODULE-$VERSION.$PACKAGING-svn.tar.bz2 egroupware/$CONTRIBMODULE 		>> $LOGFILE 2>&1
done
echo "done"										>> $LOGFILE 2>&1

echo -n "building zip ... "								>> $LOGFILE 2>&1
find $ONLY_CONTRIB > /tmp/exclude.list
zip -q -r -9 $SRCDIR/$PACKAGENAME-$VERSION.$PACKAGING-svn.zip egroupware -x@/tmp/exclude.list	>> $LOGFILE 2>&1
for CONTRIBMODULE in $EXTRAPACKAGES; do
	zip -q -r -9 $SRCDIR/$PACKAGENAME-$CONTRIBMODULE-$VERSION.$PACKAGING-svn.zip egroupware/$CONTRIBMODULE 	>> $LOGFILE 2>&1
done
echo "done"										>> $LOGFILE 2>&1

echo "Building tar.gz, tar.bz and zip archives with svn finnished"				>> $LOGFILE 2>&1

cd $NOSVNDIR
echo -n "building tar.gz ... "								>> $LOGFILE 2>&1
tar czf $SRCDIR/$PACKAGENAME-$VERSION.$PACKAGING.tar.gz $EXCLUDE_CONTRIB egroupware  	2>&1 | tee -a $LOGFILE
for CONTRIBMODULE in $EXTRAPACKAGES; do
	tar czf $SRCDIR/$PACKAGENAME-$CONTRIBMODULE-$VERSION.$PACKAGING.tar.gz egroupware/$CONTRIBMODULE 		>> $LOGFILE 2>&1
done
echo "done"										>> $LOGFILE 2>&1

echo -n "building tar.bz2 ... "						     		>> $LOGFILE 2>&1
tar cjf $SRCDIR/$PACKAGENAME-$VERSION.$PACKAGING.tar.bz2 $EXCLUDE_CONTRIB egroupware	>> $LOGFILE 2>&1
for CONTRIBMODULE in $EXTRAPACKAGES; do
	tar cjf $SRCDIR/$PACKAGENAME-$CONTRIBMODULE-$VERSION.$PACKAGING.tar.bz2 egroupware/$CONTRIBMODULE 		>> $LOGFILE 2>&1
done
echo "done"										>> $LOGFILE 2>&1

echo -n "building zip ... "								>> $LOGFILE 2>&1
find $ONLY_CONTRIB > /tmp/exclude.list
zip -q -r -9 $SRCDIR/$PACKAGENAME-$VERSION.$PACKAGING.zip egroupware -x@/tmp/exclude.list	>> $LOGFILE 2>&1
for CONTRIBMODULE in $EXTRAPACKAGES; do
	zip -q -r -9 $SRCDIR/$PACKAGENAME-$CONTRIBMODULE-$VERSION.$PACKAGING.zip egroupware/$CONTRIBMODULE 	>> $LOGFILE 2>&1
done
echo "done"										>> $LOGFILE 2>&1

echo "Building tar.gz, tar.bz and zip archives finnished"				>> $LOGFILE 2>&1

# we are no longer building extra signed source files, only a singed md5sum file
#
# echo "Create the md5sum file for tar.gz, tar.bz, zip ($MD5SUM)"				>> $LOGFILE 2>&1
# echo "Build signed source files"			    				>> $LOGFILE 2>&1
# echo "---------------------------------------"              				>> $LOGFILE 2>&1
# 
# FILENAMES="eGroupWare"
# for FILENAME in $EXTRAPACKAGES; do
# 	FILENAMES="$FILENAMES eGroupWare-$FILENAME"
# done
# 
# echo "FILENAMES: $FILENAMES"
# 
# for EXTENSION in -svn.tar.bz2 -svn.tar.gz -svn.zip .tar.bz2 .tar.gz .zip; do
# 	for f in $FILENAMES; do
# 		PACKAGENAME=$f-$VERSION.$PACKAGING$EXTENSION
# 		echo "md5sum from file $PACKAGENAME is:"  	   						>> $MD5SUM  
# 		md5sum $SRCDIR/$PACKAGENAME | cut -f1 -d' ' 						>> $MD5SUM  2>&1
# 		echo "---------------------------------------"         			   	>> $MD5SUM  2>&1
# 		echo " "						    								>> $MD5SUM  2>&1
# 
# 		echo "Build signed source files"			    					>> $LOGFILE 2>&1
# 		rm -f $SRCDIR/$PACKAGENAME.gpg			 							>> $LOGFILE 2>&1
# 		gpg --local-user packager@egroupware.org -s $SRCDIR/$PACKAGENAME 	>> $LOGFILE 2>&1
# 	done
# done
# echo "------------------------------------------"              			>> $LOGFILE 2>&1
# echo "End Build md5sum of tar.gz, tar.bz, zip"              				>> $LOGFILE 2>&1
# echo "End build of signed of tar.gz, tar.bz, zip"           				>> $LOGFILE 2>&1
# echo "------------------------------------------"              			>> $LOGFILE 2>&1

echo "------------------------------------------"              				>> $LOGFILE 2>&1
echo "Create the md5sum file for tar.gz, tar.bz, zip ($MD5SUM)"				>> $LOGFILE 2>&1
echo "------------------------------------------"              				>> $LOGFILE 2>&1

# cleaner md5sum file, the old one gave me a headache ;-)
cd $SRCDIR
for f in eGroupWare-$VERSION.$PACKAGING.*
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
