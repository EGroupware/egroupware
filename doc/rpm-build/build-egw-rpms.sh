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
# in the sript
# How to create GPG keys to sign your rpm's you will found in a seperate
# Document
#
# Script changed 2003 Sep 05 Reiner Jung

SPECFILE=egroupware.spec
SPECFILE2=egroupware-allapp.spec
                                                                                                                             
PACKAGENAME=`grep "%define packagename" $SPECFILE | cut -f3 -d' '`
VERSION=`grep "%define version" $SPECFILE | cut -f3 -d' '`
PACKAGING=`grep "%define packaging" $SPECFILE | cut -f3 -d' '`
                                                                                                                             
HOMEBUILDDIR=`whoami`
#change the variable ANONCVSDIR to your needs
ANONCVSDIR=/build_root/egroupware
RHBASE=/home/$HOMEBUILDDIR/redhat
SRCDIR=$RHBASE/SOURCES
SPECDIR=$RHBASE/SPECS
LOGFILE=$SPECDIR/build-$PACKAGENAME-$VERSION-$PACKAGING.log
MD5SUM=$SRCDIR/md5sum-$PACKAGENAME-$VERSION-$PACKAGING.txt


echo "Start Build Process of - $PACKAGENAME $VERSION"                           	 > $LOGFILE
echo "---------------------------------------"              				>> $LOGFILE 2>&1
date                                                        				>> $LOGFILE 2>&1
cd $ANONCVSDIR
cvs -z9 update -dP                                     			     		>> $LOGFILE 2>&1
echo ":pserver:anonymous@cvs.sourceforge.net:/cvsroot/egroupware" > Root.anonymous	
find . -type d -name CVS -exec cp /build_root/egroupware/Root.anonymous {}/Root \;	>> $LOGFILE 2>&1
rm Root.anonymous
echo "End from CVS update"						     		>> $LOGFILE 2>&1
echo "---------------------------------------"      				        >> $LOGFILE 2>&1


cd $ANONCVSDIR/..
tar czvf $SRCDIR/$PACKAGENAME-$VERSION-$PACKAGING.tar.gz egroupware  			>> $LOGFILE 2>&1
tar cjvf $SRCDIR/$PACKAGENAME-$VERSION-$PACKAGING.tar.bz2 egroupware 			>> $LOGFILE 2>&1
zip -r -9 $SRCDIR/$PACKAGENAME-$VERSION-$PACKAGING.zip egroupware  	  		>> $LOGFILE 2>&1
echo "End Build Process of tar.gz, tar.bz, zip"		    				>> $LOGFILE 2>&1	
echo "---------------------------------------"              				>> $LOGFILE 2>&1


echo "Create the md5sum file for tar.gz, tar.bz, zip"	    				>> $LOGFILE 2>&1	
echo "md5sum from file $PACKAGENAME-$VERSION.tar.gz is:"     				 > $MD5SUM  
md5sum $SRCDIR/$PACKAGENAME-$VERSION-$PACKAGING.tar.gz | cut -f1 -d' ' 			>> $MD5SUM  2>&1
echo "---------------------------------------"         				    	>> $MD5SUM  2>&1
echo " "						    				>> $MD5SUM  2>&1
echo "md5sum from file $PACKAGENAME-$VERSION.tar.bz2 is:"   				>> $MD5SUM  2>&1
md5sum $SRCDIR/$PACKAGENAME-$VERSION-$PACKAGING.tar.bz2 | cut -f1 -d' '			>> $MD5SUM  2>&1
echo "---------------------------------------"              				>> $MD5SUM  2>&1
echo " "						    				>> $MD5SUM  2>&1
echo "md5sum from file $PACKAGENAME-$VERSION.zip is:"       				>> $MD5SUM  2>&1
md5sum $SRCDIR/$PACKAGENAME-$VERSION-$PACKAGING.zip | cut -f1 -d' '    			>> $MD5SUM  2>&1
echo "End Build md5sum of tar.gz, tar.bz, zip"              				>> $LOGFILE 2>&1
echo "---------------------------------------"              				>> $LOGFILE 2>&1
echo "sign the md5sum file"								>> $LOGFILE 2>&1
gpg --clearsign $MD5SUM									>> $LOGFILE 2>&1
echo "---------------------------------------"              				>> $LOGFILE 2>&1


echo "Build signed source files"			    				>> $LOGFILE 2>&1
gpg -s $SRCDIR/$PACKAGENAME-$VERSION-$PACKAGING.tar.gz		    			>> $LOGFILE 2>&1
gpg -s $SRCDIR/$PACKAGENAME-$VERSION-$PACKAGING.tar.bz2		    			>> $LOGFILE 2>&1 
gpg -s $SRCDIR/$PACKAGENAME-$VERSION-$PACKAGING.zip		    			>> $LOGFILE 2>&1
echo "End build of signed of tar.gz, tar.bz, zip"           				>> $LOGFILE 2>&1
echo "---------------------------------------"              				>> $LOGFILE 2>&1



cd $SPECDIR
rpmbuild -ba --sign $SPECFILE			                    			>> $LOGFILE 2>&1
echo "End Build Process of - $PACKAGENAME $VERSION single packages"      		>> $LOGFILE 2>&1
echo "---------------------------------------"              				>> $LOGFILE 2>&1
rpmbuild -ba --sign $SPECFILE2			             				>> $LOGFILE 2>&1
echo "End Build Process of - $PACKAGENAME $VERSION all applications"     		>> $LOGFILE 2>&1
echo "---------------------------------------"      				        >> $LOGFILE 2>&1


cd $ANONCVSDIR
echo ":ext:reinerj@cvs.sourceforge.net:/cvsroot/egroupware" > Root.reinerj		
find . -type d -name CVS -exec cp /build_root/egroupware/Root.reinerj {}/Root \;	>> $LOGFILE 2>&1
rm Root.reinerj
echo "Change the CVS dir back from anonymous to CVS user"		     		>> $LOGFILE 2>&1
echo "---------------------------------------"      				        >> $LOGFILE 2>&1


