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
# Script changed 2004 May 21 Reiner Jung

SPECFILE=egroupware.spec
SPECFILE2=egroupware-allapp.spec
SPECFILEFEDORA=egroupware-fedora.spec


####
#
# Some changes for bitrock missing and delete from fedora package is not needed
#
###                                                                                                                             
PACKAGENAME=`grep "%define packagename" $SPECFILE | cut -f3 -d' '`
PACKAGENAMEFEDORA=`grep "Name:" $SPECFILEFEDORA | cut -f2 -d' '`
VERSION=`grep "%define version" $SPECFILE | cut -f3 -d' '`
VERSIONFEDORA=`grep "Version:" $SPECFILEFEDORA | cut -f2 -d' '`
PACKAGING=`grep "%define packaging" $SPECFILE | cut -f3 -d' '`
PACKAGINGFEDORA=`grep "Release:" $SPECFILEFEDORA | cut -f2 -d' '`
                                                                                                                             
HOMEBUILDDIR=`whoami`
ANONCVSDIR=/build_root/egroupware
ANONCVSDIRFEDORA=/build_root/fedora
ANONCVSDIRFEDORABUILD=/build_root/fedora/egroupware
RHBASE=/home/$HOMEBUILDDIR/redhat
SRCDIR=$RHBASE/SOURCES
SPECDIR=$RHBASE/SPECS
LOGFILE=$SPECDIR/build-$PACKAGENAME-$VERSION-$PACKAGING.log
LOGFILEFEDORA=$SPECDIR/build-$PACKAGENAMEFEDORA-$VERSIONFEDORA.$PACKAGINGFEDORA.log
LOGFILEFEBIT=$SPECDIR/build-egroupware-bitrock-$VERSIONFEDORA.$PACKAGINGFEDORA.log
VIRUSSCAN=$SPECDIR/clamav_scan-$PACKAGENAME-$VERSION-$PACKAGING.log
VIRUSSCANFEDORA=$SPECDIR/clamav_scan-$PACKAGENAMEFEDORA-$VERSIONFEDORA.$PACKAGINGFEDORA.log
MD5SUM=$SRCDIR/md5sum-$PACKAGENAME-$VERSION-$PACKAGING.txt
MD5SUMFEDORA=$SRCDIR/md5sum-$PACKAGENAMEFEDORA-$VERSIONFEDORA.$PACKAGINGFEDORA.txt


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
find . -type d -exec chmod 775 {} \;
find . -type f -exec chmod 644 {} \;
echo "Change the direcory rights back"					     		>> $LOGFILE 2>&1
echo "---------------------------------------"      				        >> $LOGFILE 2>&1

clamscan -r $ANONCVSDIR --log=$VIRUSSCAN

echo "End from Anti Virus Scan"						     		>> $LOGFILE 2>&1
echo "---------------------------------------"      				        >> $LOGFILE 2>&1

cd $ANONCVSDIR/..
tar czvf $SRCDIR/$PACKAGENAME-$VERSION-$PACKAGING.tar.gz egroupware  			>> $LOGFILE 2>&1
tar cjvf $SRCDIR/$PACKAGENAME-$VERSION-$PACKAGING.tar.bz2 egroupware 			>> $LOGFILE 2>&1
zip -r -9 $SRCDIR/$PACKAGENAME-$VERSION-$PACKAGING.zip egroupware  	  		>> $LOGFILE 2>&1
echo "End Build Process of tar.gz, tar.bz, zip"						>> $LOGFILE 2>&1	
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

echo "delete the original md5sum file"							>> $LOGFILE 2>&1
rm -rf $MD5SUM			  	 						>> $LOGFILE 2>&1
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


echo "Change the CVS dir back from anonymous to CVS user"		     		>> $LOGFILE 2>&1
echo "---------------------------------------"      				        >> $LOGFILE 2>&1
cd $ANONCVSDIR
echo ":ext:reinerj@cvs.sourceforge.net:/cvsroot/egroupware" > Root.reinerj		
find . -type d -name CVS -exec cp /build_root/egroupware/Root.reinerj {}/Root \;	>> $LOGFILE 2>&1
rm Root.reinerj
echo "Change the direcory rights back"					     		>> $LOGFILE 2>&1
echo "---------------------------------------"      				        >> $LOGFILE 2>&1
find . -type d -exec chmod 775 {} \;
find . -type f -exec chmod 644 {} \;


##############################################################################################################
#                                                                                                            # 
# Here start the build process for the Fedora packages                                                       #
#                                                                                                            #
############################################################################################################## 


echo "Start Build Process of - $PACKAGENAMEFEDORA $VERSIONFEDORA $PACKAGINGFEDORA"       > $LOGFILEFEDORA
echo "---------------------------------------"                                          >> $LOGFILEFEDORA 2>&1
date                                                                                    >> $LOGFILEFEDORA 2>&1
cd $ANONCVSDIRFEDORA
cvs -z3 -d:ext:reinerj@cvs.sourceforge.net:/cvsroot/egroupware co egroupware		>> $LOGFILEFEDORA 2>&1
echo "must wait to finish this job"
cd $ANONCVSDIRFEDORABUILD	                                                        >> $LOGFILEFEDORA 2>&1
cvs co all                                                                              >> $LOGFILEFEDORA 2>&1
echo "first files are updated and now we must delete the old ones"  			>> $LOGFILEFEDORA 2>&1
cvs -z9 update -dP 									>> $LOGFILEFEDORA 2>&1
find . -type d -name CVS | xargs rm -rf
find . -type d -exec chmod 775 {} \;
find . -type f -exec chmod 644 {} \;

echo "End from Fedora CVS update"                                                       >> $LOGFILEFEDORA 2>&1
echo "---------------------------------------"                                          >> $LOGFILEFEDORA 2>&1
                                                                                                                             
clamscan -r $ANONCVSDIRFEDORABUILD --log=$VIRUSSCANDEFORA
                                                                                                                             
echo "End from Fedora Anti Virus Scan"                                                  >> $LOGFILEFEDORA 2>&1
echo "---------------------------------------"                                          >> $LOGFILEFEDORA 2>&1
                                                                                                                             
cd $ANONCVSDIRFEDORA
tar czvf $SRCDIR/$PACKAGENAMEFEDORA-$VERSIONFEDORA.$PACKAGINGFEDORA.tar.gz egroupware   >> $LOGFILEFEDORA 2>&1
                                                                                                                             
                                                                                                                             
echo "Start Build Process of - $PACKAGENAMEFEDORA $VERSIONFEDORA"                       >> $LOGFILEFEDORA 2>&1
echo "---------------------------------------"                                          >> $LOGFILEFEDORA 2>&1
cd $SPECDIR
rpmbuild -ba --sign $SPECFILEFEDORA                                                     >> $LOGFILEFEDORA 2>&1
echo "End Build Process of - $PACKAGENAMEFEDORA $VERSIONFEDORA $PACKAGINGFEDORA"        >> $LOGFILEFEDORA 2>&1
echo "---------------------------------------"                                          >> $LOGFILEFEDORA 2>&1
                                                                                                                             


##############################################################################################################
#                                                                                                            #
# Here start the build process for Bitrock packages                                                          #
#                                                                                                            #
##############################################################################################################


echo "Start build Bitrock packages"	                                                 > $LOGFILEFEBIT
echo "---------------------------------------"                                          >> $LOGFILEFEBIT 2>&1
date                                                                                    >> $LOGFILEFEBIT 2>&1

cd $ANONCVSDIRFEDORA
                                                                                                                            
/opt/installbuilder-1.0.2/bin/builder build /opt/installbuilder-1.0.2/projects/egroupware.xml

rm -rf egroupware
echo "Fedora Build Root deleted $PACKAGENAMEFEDORA $VERSIONFEDORA $PACKAGINGFEDORA"     >> $LOGFILEFEBIT 2>&1
echo "---------------------------------------"                                          >> $LOGFILEFEBIT 2>&1


