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

BRANCH="-r Version-1_2_0-branch"
#BRANCH=-A

SPECFILE=`(if test -f /etc/SuSE-release; then echo egroupware-suse-php5.spec; else echo egroupware-fedora.spec; fi)`
SPECFILE=egroupware-suse-php5.spec
SOURCEFILES="egroupware_fedora.tar.bz2 egroupware_suse.tar.bz2 manageheader.php.patch class.uiasyncservice.inc.php.patch"

CONTRIB="backup browser comic chatty email egwical filescenter forum ftp fudforum headlines icalsrv jinn messenger phpldapadmin projects stocks switchuser tts skel soap xmlrpc"

for p in $CONTRIB
do
   EXCLUDE_CONTRIB="$EXCLUDE_CONTRIB --exclude=egroupware/$p"
   ONLY_CONTRIB="$ONLY_CONTRIB egroupware/$p"
done

####
#
# Some changes for bitrock missing and delete from fedora package is not needed
#
###                                                                                                                             
PACKAGENAME=`grep "%define packagename" $SPECFILE | cut -f3 -d' '`
VERSION=`grep "%define egwversion" $SPECFILE | cut -f3 -d' '`
PACKAGING=`grep "%define packaging" $SPECFILE | cut -f3 -d' '`
                                                                                                                             
HOMEBUILDDIR=`whoami`
#which account to use for checkouts and updates, after that the tree is made anonymous anyway, to allow users to update
CVSACCOUNT=ext:lkneschke
#CVSACCOUNT=pserver:anonymous
ANONCVSDIR=/tmp/build_root/egroupware
RHBASE=$HOME/rpm
SRCDIR=$RHBASE/SOURCES
SPECDIR=$RHBASE/SPECS
LOGFILE=$RHBASE/LOG/build-$PACKAGENAME-$VERSION-$PACKAGING.log
VIRUSSCAN=$SPECDIR/clamav_scan-$PACKAGENAME-$VERSION-$PACKAGING.log
MD5SUM=$SRCDIR/md5sum-$PACKAGENAME-$VERSION-$PACKAGING.txt

mkdir -p $RHBASE/SOURCES $RHBASE/SPECS $RHBASE/BUILD $RHBASE/SRPMS $RHBASE/RPMS $RHBASE/LOG $ANONCVSDIR

cp *.spec $RHBASE/SPECS/
cp $SOURCEFILES $RHBASE/SOURCES/
echo "Starting build process of - $PACKAGENAME $VERSION"                     		>  $LOGFILE
echo ""											>> $LOGFILE 2>&1
date                                                        				>> $LOGFILE 2>&1
cd $ANONCVSDIR
	
[ "$CVSACCOUNT" = 'pserver:anonymous' ] && CVS_RSH="ssh" cvs -d:$CVSACCOUNT@cvs.sourceforge.net:/cvsroot/egroupware login

if [ ! -d egroupware/phpgwapi ]	# new checkout
then
	echo -n "Creating a new checkout using $CVSACCOUNT ... "			>> $LOGFILE 2>&1
	svn checkout http://svn.egroupware.org/egroupware/branches/1.2/aliases/all ./
else										# updating an existing checkout in the build-root
	echo -n "Updating existing checkout using $CVSACCOUNT ... "			>> $LOGFILE 2>&1
	cd egroupware						# need to step into the eGW dir (no CVS dir otherwise)
	svn update
fi

cd $ANONCVSDIR

echo "done"										>> $LOGFILE 2>&1

echo -n "Change the direcory rights back ... "						>> $LOGFILE 2>&1
find . -type d -exec chmod 775 {} \;
find . -type f -exec chmod 644 {} \;
echo "done"										>> $LOGFILE 2>&1

echo -n "Starting anti virus scan ... "							>> $LOGFILE 2>&1
test -x /usr/bin/clamscan && /usr/bin/clamscan --quiet -r $ANONCVSDIR --log=$VIRUSSCAN
echo "done"										>> $LOGFILE 2>&1

cd $ANONCVSDIR
echo -n "building tar.gz ... "								>> $LOGFILE 2>&1
tar czf $SRCDIR/$PACKAGENAME-$VERSION-$PACKAGING.tar.gz $EXCLUDE_CONTRIB egroupware  	2>&1 | tee -a $LOGFILE
tar czf $SRCDIR/$PACKAGENAME-contrib-$VERSION-$PACKAGING.tar.gz $ONLY_CONTRIB 		>> $LOGFILE 2>&1
echo "done"										>> $LOGFILE 2>&1

echo -n "building tar.bz2 ... "						     		>> $LOGFILE 2>&1
tar cjf $SRCDIR/$PACKAGENAME-$VERSION-$PACKAGING.tar.bz2 $EXCLUDE_CONTRIB egroupware	>> $LOGFILE 2>&1
tar cjf $SRCDIR/$PACKAGENAME-contrib-$VERSION-$PACKAGING.tar.bz2 $ONLY_CONTRIB 		>> $LOGFILE 2>&1
echo "done"										>> $LOGFILE 2>&1

echo -n "building zip ... "								>> $LOGFILE 2>&1
find $ONLY_CONTRIB > /tmp/exclude.list
zip -q -r -9 $SRCDIR/$PACKAGENAME-$VERSION-$PACKAGING.zip egroupware -x@/tmp/exclude.list	>> $LOGFILE 2>&1
zip -q -r -9 $SRCDIR/$PACKAGENAME-contrib-$VERSION-$PACKAGING.zip $ONLY_CONTRIB 	>> $LOGFILE 2>&1
echo "done"										>> $LOGFILE 2>&1

echo "Building tar.gz, tar.bz and zip archives finnished"				>> $LOGFILE 2>&1

echo "Create the md5sum file for tar.gz, tar.bz, zip ($MD5SUM)"				>> $LOGFILE 2>&1
echo "Build signed source files"			    				>> $LOGFILE 2>&1
echo "---------------------------------------"              				>> $LOGFILE 2>&1

for f in $VERSION-$PACKAGING.tar.gz contrib-$VERSION-$PACKAGING.tar.gz $VERSION-$PACKAGING.tar.bz2 contrib-$VERSION-$PACKAGING.tar.bz2 $VERSION-$PACKAGING.zip contrib-$VERSION-$PACKAGING.zip
do
	echo "md5sum from file $PACKAGENAME-$f is:"     				>> $MD5SUM  
	md5sum $SRCDIR/$PACKAGENAME-$f | cut -f1 -d' ' 					>> $MD5SUM  2>&1
	echo "---------------------------------------"         			    	>> $MD5SUM  2>&1
	echo " "						    			>> $MD5SUM  2>&1

	echo "Build signed source files"			    			>> $LOGFILE 2>&1
	rm -f $SRCDIR/$PACKAGENAME-$f.gpg		 				>> $LOGFILE 2>&1
	gpg --local-user packager@egroupware.org -s $SRCDIR/$PACKAGENAME-$f 		>> $LOGFILE 2>&1
done
echo "------------------------------------------"              				>> $LOGFILE 2>&1
echo "End Build md5sum of tar.gz, tar.bz, zip"              				>> $LOGFILE 2>&1
echo "End build of signed of tar.gz, tar.bz, zip"           				>> $LOGFILE 2>&1
echo "------------------------------------------"              				>> $LOGFILE 2>&1

echo "sign the md5sum file"								>> $LOGFILE 2>&1
rm -f $MD5SUM.asc									>> $LOGFILE 2>&1
gpg --local-user packager@egroupware.org --clearsign $MD5SUM				>> $LOGFILE 2>&1
echo "---------------------------------------"              				>> $LOGFILE 2>&1

echo "delete the original md5sum file"							>> $LOGFILE 2>&1
rm -rf $MD5SUM			  	 						>> $LOGFILE 2>&1
echo "---------------------------------------"              				>> $LOGFILE 2>&1

cd $SPECDIR
if test -f /etc/SuSE-release; then 
	echo -n "Building SUSE10.0 PHP4 RPM's ... "					>> $LOGFILE 2>&1
	rpmbuild -ba --sign egroupware-suse-php4.spec					2>&1 | tee $RHBASE/LOG/egroupware-suse-php4.log
	echo "done"									>> $LOGFILE 2>&1
	echo -n "Building SUSE10.0 PHP5 RPM's ... "					>> $LOGFILE 2>&1
	rpmbuild -ba --sign egroupware-suse-php5.spec					2>&1 | tee $RHBASE/LOG/egroupware-suse-php5.log
	echo "done"									>> $LOGFILE 2>&1
else
	echo -n "Building Fedora Core4 RPM's ... "					>> $LOGFILE 2>&1
	rpmbuild -ba --sign egroupware-fedora4.spec					2>&1 | tee $RHBASE/LOG/egroupware-fedora4.log
	echo "done"									>> $LOGFILE 2>&1
fi
echo "Building of $PACKAGENAME $VERSION finnished"					>> $LOGFILE 2>&1
