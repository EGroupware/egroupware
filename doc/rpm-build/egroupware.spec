%define packagename eGroupWare
%define egwdirname egroupware
%define version 1.0.00.006
%define packaging 1
%define epoch 0
%define httpdroot  %(if test -f /etc/SuSE-release; then echo /srv/www/htdocs; else echo /var/www/html; fi)

%define addressbook addressbook
%define backup backup
%define bookmarks bookmarks
%define calendar calendar
%define comic comic
%define developer_tools developer_tools
%define docs doc
%define email email
%define emailadmin emailadmin
%define etemplate etemplate
%define felamimail felamimail
%define filemanager filemanager
%define forum forum
%define ftp ftp
%define fudforum fudforum
%define headlines headlines
%define infolog infolog
%define jinn jinn
%define manual manual
%define messenger messenger
%define news_admin news_admin
%define phpldapadmin phpldapadmin
%define phpbrain phpbrain
%define phpsysinfo phpsysinfo
%define polls polls
%define projects projects
%define registration registration
%define sitemgr sitemgr
%define skel skel
%define stocks stocks
%define tts tts
%define wiki wiki

Name: %{packagename}
Version: %{version}
Release: %{packaging}
Epoch: %{epoch}
Summary: eGroupWare is a web-based groupware suite written in php. 

Group: Web/Database
License: GPL/LGPL
URL: http://www.egroupware.org/
Source0:  http://download.sourceforge.net/egroupware/%{packagename}-%{version}-%{packaging}.tar.bz2
BuildRoot: /tmp/%{packagename}-buildroot
Requires: php >= 4.0.6

Prefix: %{httpdroot}
Buildarch: noarch
AutoReqProv: no

Vendor: eGroupWare
Packager: eGroupWare <r.jung@creativix.net>

%description
eGroupWare is a web-based groupware suite written in PHP. 
The core package provides the admin, setup, phpgwapi and preferences
packages. It also provides an API for developing additional applications. 
See the egroupware apps project for add-on apps.

%package %{addressbook}
Summary: The eGroupWare %{addressbook} application
Group: Web/Database
AutoReqProv: no
Requires: eGroupWare = %{version}-%{packaging}
%description %{addressbook}
Contact manager with Vcard support.
%{addressbook} is the egroupware default contact application.
It makes use of the egroupware contacts class to store and retrieve 
contact information via SQL, LDAP or Active Directory.

%package %{backup}
Summary: The eGroupWare %{backup} application
Group: Web/Database
AutoReqProv: no
Requires: eGroupWare = %{version}-%{packaging}
%description %{backup}
An online configurable backup app to store data offline. 
Can store files in zip, tar.gz and tar.bz2 on the local machine 
or Remote via FTP, SMBMOUNT or NFS 

%package %{bookmarks}
Summary: The eGroupWare %{bookmarks} application
Group: Web/Database
AutoReqProv: no
Requires: eGroupWare = %{version}-%{packaging}
%description %{bookmarks}
Manage your bookmarks with eGroupWare. Has Netscape plugin.

%package %{calendar}
Summary: The eGroupWare %{calendar} application
Group: Web/Database
AutoReqProv: no
Requires: eGroupWare = %{version}-%{packaging}
%description %{calendar}
Powerful calendar with meeting request system, Alarms, ICal and E-Mail support, 
and ACL security.

%package %{comic}
Summary: The eGroupWare %{comic} application
Group: Web/Database
AutoReqProv: no
Requires: eGroupWare = %{version}-%{packaging}
%description %{comic}
This application display comic strips.

%package %{developer_tools}
Summary: The eGroupWare %{developer_tools} application
Group: Web/Database
AutoReqProv: no
Requires: eGroupWare = %{version}-%{packaging}
%description %{developer_tools}
The TranslationTools allow to create and extend translations-files for eGroupWare. They can search the sources for new / added phrases and show you the ones missing in your language. 

%package %{docs}
Summary: The eGroupWare %{docs}
Group: Web/Database
AutoReqProv: no
Requires: eGroupWare = %{version}-%{packaging}
%description %{docs}
This is the %{docs} for eGroupWare.

%package %{email}
Summary: The eGroupWare %{email} application
Group: Web/Database
AutoReqProv: no
Requires: eGroupWare = %{version}-%{packaging}, eGroupWare-addressbook = %{version}-%{packaging}
%description %{email}
AngleMail for eGroupWare at www.anglemail.org is an Email reader with multiple accounts and mailbox filtering. Also Anglemail support IMAP, IMAPS, POP3 and POP3S accounts.

%package %{emailadmin}
Summary: The eGroupWare %{emailadmin} application
Group: Web/Database
AutoReqProv: no
Requires: eGroupWare = %{version}-%{packaging}
%description %{emailadmin}
EmailAdmin allow to maintain User email accounts 

%package %{etemplate}
Summary: The eGroupWare %{etemplate} application
Group: Web/Database
AutoReqProv: no
Requires: eGroupWare = %{version}-%{packaging}, eGroupWare-addressbook = %{version}-%{packaging}
%description %{etemplate}
eTemplates are a new widget-based template system for eGroupWare with an interactive editor and a database table-editor (creates tables_current.inc.php and updates automaticaly tables_update.inc.php)

%package %{felamimail}
Summary: The eGroupWare %{felamimail} application
Group: Web/Database
AutoReqProv: no
Requires: eGroupWare = %{version}-%{packaging}, eGroupWare-emailadmin = %{version}-%{packaging}
%description %{felamimail}
The %{felamimail} Email Reader is a other Email application for phpgw eGroupWare.

%package %{filemanager}
Summary: The eGroupWare %{filemanager} application
Group: Web/Database
AutoReqProv: no
Requires: eGroupWare = %{version}-%{packaging}
%description %{filemanager}
This is the %{filemanager} app for eGroupWare.

%package %{forum}
Summary: The eGroupWare %{forum} application
Group: Web/Database
AutoReqProv: no
Requires: eGroupWare = %{version}-%{packaging}
%description %{forum}
This is the %{forum} app for eGroupWare.

%package %{ftp}
Summary: The eGroupWare %{ftp} application
Group: Web/Database
AutoReqProv: no
Requires: eGroupWare = %{version}-%{packaging} 
%description %{ftp}
This is the %{ftp} app for eGroupWare.

%package %{fudforum}
Summary: The eGroupWare %{fudforum} application
Group: Web/Database
AutoReqProv: no
Requires: eGroupWare = %{version}-%{packaging}
%description %{fudforum}
This is the %{fudforum} app for eGroupWare.

%package %{headlines}
Summary: The eGroupWare %{headlines} application
Group: Web/Database
AutoReqProv: no
Requires: eGroupWare = %{version}-%{packaging} 
%description %{headlines}
This is the %{headlines} app for eGroupWare.

%package %{infolog}
Summary: The eGroupWare %{infolog} application
Group: Web/Database
AutoReqProv: no
Requires: eGroupWare = %{version}-%{packaging}, eGroupWare-etemplate = %{version}-%{packaging}
%description %{infolog}
This is the %{infolog} app for eGroupWare.

%package %{jinn}
Summary: The eGroupWare %{jinn} application
Group: Web/Database
AutoReqProv: no
Requires: eGroupWare = %{version}-%{packaging}
%description %{jinn}
The %{jinn} app is a multi-site, multi-database, multi-user/-group, database driven Content Management System written in and for the eGroupWare Framework.

%package %{manual}
Summary: The eGroupWare %{manual} application
Group: Web/Database
AutoReqProv: no
Requires: eGroupWare = %{version}-%{packaging}
%description %{manual}
This is the %{manual} app for eGroupWare.

%package %{messenger}
Summary: The eGroupWare %{messenger} application
Group: Web/Database
AutoReqProv: no
Requires: eGroupWare = %{version}-%{packaging} 
%description %{messenger}
This is the %{messenger} app for eGroupWare.

%package %{news_admin}
Summary: The eGroupWare %{news_admin} application
Group: Web/Database
AutoReqProv: no
Requires: eGroupWare = %{version}-%{packaging} 
%description %{news_admin}
This is the %{news_admin} app for eGroupWare.

%package %{phpbrain}
Summary: The eGroupWare %{phpbrain} application
Group: Web/Database
AutoReqProv: no
Requires: eGroupWare = %{version}-%{packaging}, eGroupWare-addressbook = %{version}-%{packaging}
%description %{phpbrain}
This is the %{phpbrain} app for eGroupWare.

%package %{phpldapadmin}
Summary: The eGroupWare %{phpldapadmin} application
Group: Web/Database
AutoReqProv: no
Requires: eGroupWare = %{version}-%{packaging}
%description %{phpldapadmin}
This is the cire %{phpldapadmin} of eGroupWare.

%package %{phpsysinfo}
Summary: The eGroupWare %{phpsysinfo} application
Group: Web/Database
AutoReqProv: no
Requires: eGroupWare = %{version}-%{packaging}
%description %{phpsysinfo}
This is the cire %{phpsysinfo} of eGroupWare.

%package %{polls}
Summary: The eGroupWare %{polls} application
Group: Web/Database
AutoReqProv: no
Requires: eGroupWare = %{version}-%{packaging}
%description %{polls}
This is the %{polls} app for eGroupWare.

%package %{projects}
Summary: The eGroupWare %{projects} application
Group: Web/Database
AutoReqProv: no
Requires: eGroupWare = %{version}-%{packaging}, eGroupWare-addressbook = %{version}-%{packaging}
%description %{projects}
This is the %{projects} app for eGroupWare.

%package %{registration}
Summary: The eGroupWare %{registration} application
Group: Web/Database
AutoReqProv: no
Requires: eGroupWare = %{version}-%{packaging}
%description %{registration}
This is the %{registration} app for eGroupWare.

%package %{skel}
Summary: The eGroupWare Skeleton application
Group: Web/Database
AutoReqProv: no
Requires: eGroupWare = %{version}-%{packaging}
%description %{skel}
This is the Skeleton app for eGroupWare.

%package %{sitemgr}
Summary: The eGroupWare Sitemanager CMS application
Group: Web/Database
AutoReqProv: no
Requires: eGroupWare = %{version}-%{packaging}
%description %{sitemgr}
This is the Sitemanager CMS app for eGroupWare.

%package %{stocks}
Summary: The eGroupWare %{stocks} application
Group: Web/Database
AutoReqProv: no
Requires: eGroupWare = %{version}-%{packaging}
%description %{stocks}
This is the %{stocks} app for eGroupWare.

%package %{tts}
Summary: The eGroupWare trouble ticket system application
Group: Web/Database
AutoReqProv: no
Requires: eGroupWare = %{version}-%{packaging}
%description %{tts}
This is the trouble ticket system} app for eGroupWare.

%package %{wiki}
Summary: The eGroupWare %{wiki} application
Group: Web/Database
AutoReqProv: no
Requires: eGroupWare = %{version}-%{packaging}, eGroupWare-etemplate = %{version}-%{packaging}
%description %{wiki}
This is the %{wiki} app for eGroupWare.

%prep
%setup -n %{egwdirname}

%build
# no build required

%install
[ "%{buildroot}" != "/" ] && rm -rf %{buildroot}
mkdir -p $RPM_BUILD_ROOT%{prefix}/%{egwdirname}
cp -aRf * $RPM_BUILD_ROOT%{prefix}/%{egwdirname}
#cp .htaccess $RPM_BUILD_ROOT%{prefix}/%{egwdirname}

%clean
[ "%{buildroot}" != "/" ] && rm -rf %{buildroot}

%post

                                                                                                                             
    echo "***************************************************"
    echo "* Attention: You must create the folder FILES     *"
    echo "* manually outside the root from your             *"
    echo "* webserver root.                                 *"
    echo "* The folder must include the folders users and   *"
    echo "* files like: /var/www/                           *"
    echo "*                      egwfiles/                  *"
    echo "*                                 users           *"
    echo "*                                 groups          *"
    echo "* Give the webserver the rights to read and write *"
    echo "* and no anonymous access to this folders         *"
    echo "* *************************************************"
    echo "* Please secure you apache and add                *"
    echo "* the follow lines to you httpd.conf              *"
    echo "*                                                 *"
    echo "* <Directory /var/www/html/egroupware>            *"
    echo "*   <Files ~ "\.\(inc.php\|tpl\)$">               *"
    echo "*      Order allow,deny                           *"
    echo "*      Deny from all                              *"
    echo "*    </Files>                                     *"
    echo "* </Directory>                                    *"
    echo "***************************************************"

%postun

%files
%defattr(0744,root,root)
%dir %{prefix}/%{egwdirname}
%{prefix}/%{egwdirname}/home.php
%{prefix}/%{egwdirname}/about.php
%{prefix}/%{egwdirname}/anon_wrapper.php
%{prefix}/%{egwdirname}/notify.php
%{prefix}/%{egwdirname}/notify_simple.php
%{prefix}/%{egwdirname}/redirect.php
%{prefix}/%{egwdirname}/set_box.php
%{prefix}/%{egwdirname}/xmlrpc.php
%{prefix}/%{egwdirname}/soap.php
%{prefix}/%{egwdirname}/header.inc.php.template
%{prefix}/%{egwdirname}/index.php
%{prefix}/%{egwdirname}/login.php
%{prefix}/%{egwdirname}/logout.php
%{prefix}/%{egwdirname}/CVS
%{prefix}/%{egwdirname}/doc
%{prefix}/%{egwdirname}/phpgwapi
%{prefix}/%{egwdirname}/admin
%{prefix}/%{egwdirname}/preferences
%{prefix}/%{egwdirname}/setup

%files %{addressbook}
%defattr(0744,root,root)
%{prefix}/%{egwdirname}/%{addressbook}

%files %{backup}
%defattr(0744,root,root)
%{prefix}/%{egwdirname}/%{backup}

%files %{bookmarks}
%defattr(0744,root,root)
%{prefix}/%{egwdirname}/%{bookmarks}

%files %{calendar}
%defattr(0744,root,root)
%{prefix}/%{egwdirname}/%{calendar}

%files %{comic}
%defattr(0744,root,root)
%{prefix}/%{egwdirname}/%{comic}

%files %{developer_tools}
%defattr(0744,root,root)
%{prefix}/%{egwdirname}/%{developer_tools}

%files %{email}
%defattr(0744,root,root)
%{prefix}/%{egwdirname}/%{email}

%files %{emailadmin}
%defattr(0744,root,root)
%{prefix}/%{egwdirname}/%{emailadmin}

%files %{etemplate}
%defattr(0744,root,root)
%{prefix}/%{egwdirname}/%{etemplate}

%files %{felamimail}
%defattr(0744,root,root)
%{prefix}/%{egwdirname}/%{felamimail}

%files %{filemanager}
%defattr(0744,root,root)
%{prefix}/%{egwdirname}/%{filemanager}

%files %{forum}
%defattr(0744,root,root)
%{prefix}/%{egwdirname}/%{forum}

%files %{ftp}
%defattr(0744,root,root)
%{prefix}/%{egwdirname}/%{ftp}

%files %{fudforum}
%defattr(0744,root,root)
%{prefix}/%{egwdirname}/%{fudforum}

%files %{headlines}
%defattr(0744,root,root)
%{prefix}/%{egwdirname}/%{headlines}

%files %{infolog}
%defattr(0744,root,root)
%{prefix}/%{egwdirname}/%{infolog}

%files %{jinn}
%defattr(0744,root,root)
%{prefix}/%{egwdirname}/%{jinn}

%files %{manual}
%defattr(0744,root,root)
%{prefix}/%{egwdirname}/%{manual}

%files %{messenger}
%defattr(0744,root,root)
%{prefix}/%{egwdirname}/%{messenger}

%files %{news_admin}
%defattr(0744,root,root)
%{prefix}/%{egwdirname}/%{news_admin}

%files %{phpbrain}
%defattr(0744,root,root)
%{prefix}/%{egwdirname}/%{phpbrain}

%files %{phpldapadmin}
%defattr(0744,root,root)
%{prefix}/%{egwdirname}/%{phpldapadmin}

%files %{phpsysinfo}
%defattr(0744,root,root)
%{prefix}/%{egwdirname}/%{phpsysinfo}

%files %{polls}
%defattr(0744,root,root)
%{prefix}/%{egwdirname}/%{polls}

%files %{projects}
%defattr(0744,root,root)
%{prefix}/%{egwdirname}/%{projects}

%files %{registration}
%defattr(0744,root,root)
%{prefix}/%{egwdirname}/%{registration}

%files %{sitemgr}
%defattr(0744,root,root)
%{prefix}/%{egwdirname}/%{sitemgr}

%files %{skel}
%defattr(0744,root,root)
%{prefix}/%{egwdirname}/%{skel}

%files %{stocks}
%defattr(0744,root,root)
%{prefix}/%{egwdirname}/%{stocks}

%files %{tts}
%defattr(0744,root,root)
%{prefix}/%{egwdirname}/%{tts}

%files %{wiki}
%defattr(0744,root,root)
%{prefix}/%{egwdirname}/%{wiki}

%changelog
* Sat Nov 06 2004 Reiner Jung <r.jung@creativix.net> 1.0.00.006-1
- Fix a security problem in JiNN application
- Bugfixes

* Wed Sep 08 2004 Reiner Jung <r.jung@creativix.net> 1.0.00.005-1
- Bugfix release

* Thu Aug 24 2004 Reiner Jung <r.jung@creativix.net> 1.0.00.004-2
- Bugfix for Email after security patch

* Mon Aug 23 2004 Reiner Jung <r.jung@creativix.net> 1.0.00.004-1
- Security release fixes several XSS problems

* Sat Aug 07 2004 Reiner Jung <r.jung@creativix.net> 1.0.00.003-1
- Final 1.0 release from eGroupWare
- some bugs fixed

* Sat Jul 31 2004 Reiner Jung <r.jung@creativix.net> 1.0.00.002-1
- critical bugs fixed
- MS SQL server support is back
- language extensions

* Sun Jul 11 2004 Reiner Jung <r.jung@creativix.net> 1.0.00.001-1
- bug fixing in all applications

* Thu Jun 29 2004 Reiner Jung <r.jung@creativix.net> 0.9.99.026-1
- JiNN extended. 
- projects updated
- new knowledge base available
- new language available Catalan
- many languages updated
- bug fixes in all applications
- extend the usage of indexes for DB tables

* Thu Apr 27 2004 Reiner Jung <r.jung@creativix.net> 0.9.99.015-1
- rewrite of projects added. 
- Wiki with WYSIWYG editor added
- bugfixes for sitemgr
- email don't need longer php-imap module, many bugfixes for email included
- Traditional Chinese lang updated
- Danish lang updated
- Italien lang files updated
- Russian translation started
- jerryr template updated
- many bugs fixed in all applications

* Wed Mar 03 2004 Reiner Jung <r.jung@creativix.net> 0.9.99.014-1
- add support to spec file for SuSE directory structure. 
  When you want build packages for SuSE, please download the source RPM and make
  rpmbuild --rebuild eGroupWare.xxxxx.spec.
- extensions to Danish language
- extensions at sitemgr
- bugfixes for upcomming 1.0 release

* Sat Feb 07 2004 Reiner Jung <r.jung@creativix.net> 0.9.99.013-2
- bugfix for broken calender ACL

* Sat Feb 07 2004 Reiner Jung <r.jung@creativix.net> 0.9.99.013-1
- Release RC3-3 is only a small bugfixing for some installations
- PostgreSQL bug fixed
- Email Bug fixed

* Wed Jan 28 2004 Reiner Jung <r.jung@creativix.net> 0.9.99.012-2
- We use the download problem at out server buf fix some other problems

* Wed Jan 28 2004 Reiner Jung <r.jung@creativix.net> 0.9.99.012-1
- remove justweb template
- Skel app added as package
- Messenger back in eGW
- Spanish translation finished
- Ukrain translation added to eGW and more than 50% finished
- extensions on Italian translation
- backup rewrite
- Poll upp is rewrited
- Knowledge Base rewrite (start from new killer app support center)
- sitemgr fist preview of 1.0 candidate
- extension on idots
- new template set included jerryr (preview to 1.0 version)
- felamimail extension (folders)
- email bugfixes and some nice extensions
- encrytion from passwords for header.inc.php and database passwords added
- username case sensitive
- JiNN CMS updated
- addressbook import extended
- wiki some extensions
- many Bugs fixed
- fudforum available in a updated version

* Mon Dec 22 2003 Reiner Jung <r.jung@creativix.net> 0.9.99.008-2
- Bug fix for PostgreSQL error.

* Mon Dec 22 2003 Reiner Jung <r.jung@creativix.net> 0.9.99.008-1
- Many Bugs fixed.
- Extension in Idots
- fuforum updated
- Registration application working again

* Mon Dec 08 2003 Reiner Jung <r.jung@creativix.net> 0.9.99.008
- Many Bugs fixed.
- First available version from phpldapadmin
- Dutch, Slovenia, Brasilien Portuguese and Chinese translation extended
- mass delete entries in calender
- setup support DB ports

* Mon Nov 03 2003 Reiner Jung <r.jung@creativix.net> 0.9.99.006
- Many Bugs fixed.
- First available version from FUDeGW forum
- pre checking the php and folders
- idots template extended 

* Fri Oct 10 2003 Reiner Jung <r.jung@creativix.net> 0.9.99.005
- Many Bugs fixed.
- TTS with Petri Net Support
- CSV import to Calendar, Infolog
- Projects app extendet and 1st preview from gant charts available
- Simplified Chinese translation added
- Experimental, internal usage from UTF-8 available
- New layout for setup

* Wed Sep 25 2003 Reiner Jung <r.jung@creativix.net> 0.9.99.004
- Bugfix release.

* Mon Sep 08 2003 Reiner Jung <r.jung@creativix.net> 0.9.99.001
- update possibility via CVS
- Headlines bugfixes and new gray theme
- Import from new anglemail
- small changes and bugfixes in Infolog
- calendar show now phone calls, notes and todos
- asyncservice problem fixed
- wiki bugfixes
- felamimail
- improved displaying of messages. added some javascript code, to make switching beetwen message, attachments and header lines faster. Updated the layout of the main page and the message display page to look better. Added support for emailadmin. felamimail needs now emailadmin to beinstalled.

* Sat Aug 30 2003 Reiner Jung <r.jung@creativix.net> 0.9.99.000
- initianal eGroupWare package anouncement.

