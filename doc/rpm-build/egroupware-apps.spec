%define packagename eGroupWare
%define egwdirname egroupware
%define version 1.2RC6
%define packaging 1
%define epoch 0
%define httpdroot  %(if test -f /etc/SuSE-release; then echo /srv/www/htdocs; else echo /var/www/html; fi)

%define addressbook addressbook
%define backup backup
%define browser browser
%define bookmarks bookmarks
%define calendar calendar
%define chatty chatty
%define comic comic
%define developer_tools developer_tools
%define email email
%define emailadmin emailadmin
%define felamimail felamimail
%define filescenter filescenter
%define filemanager filemanager
%define forum forum
%define ftp ftp
%define fudforum fudforum
%define headlines headlines
%define infolog infolog
%define jinn jinn
%define manual manual
%define messenger messenger
%define mydms mydms
%define news_admin news_admin
%define phpldapadmin phpldapadmin
%define phpbrain phpbrain
%define phpsysinfo phpsysinfo
%define polls polls
%define projects projects
%define projectmanager projectmanager
%define registration registration
%define resources resources
%define sambaadmin sambaadmin
%define sitemgr sitemgr
%define stocks stocks
%define switchuser switchuser
%define syncml syncml
%define timesheet timesheet
%define tts tts
%define wiki wiki
%define workflow workflow

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
Requires: php >= 4.3

Prefix: %{httpdroot}
Buildarch: noarch
AutoReqProv: no

Vendor: eGroupWare
Packager: eGroupWare <RalfBecker@outdoor-training.de>

%description
eGroupWare is a web-based groupware suite written in PHP. 
The core package provides the admin, etemplate, phpgwapi, preferences
and setup applications. 
It also provides an API for developing additional applications. 

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

%package %{browser}
Summary: The eGroupWare %{browser} application
Group: Web/Database
AutoReqProv: no
Requires: eGroupWare = %{version}-%{packaging}
%description %{browser}
Intergrated browser to surf the web within eGroupWare.

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

%package %{chatty}
Summary: Instant messenger for eGroupWare
Group: Web/Database
AutoReqProv: no
Requires: eGroupWare = %{version}-%{packaging}
%description %{chatty}
Instant messenger application using AJAX.

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
The TranslationTools allow to create and extend translations-files for eGroupWare. 
They can search the sources for new / added phrases and show you the ones missing in your language. 

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

%package %{felamimail}
Summary: The eGroupWare %{felamimail} application
Group: Web/Database
AutoReqProv: no
Requires: eGroupWare = %{version}-%{packaging}, eGroupWare-emailadmin = %{version}-%{packaging}
%description %{felamimail}
The %{felamimail} Email Reader is a other Email application for eGroupWare.

%package %{filemanager}
Summary: The eGroupWare %{filemanager} application
Group: Web/Database
AutoReqProv: no
Requires: eGroupWare = %{version}-%{packaging}
%description %{filemanager}
This is the %{filemanager} app for eGroupWare.

%package %{filescenter}
Summary: The eGroupWare %{filescenter} application
Group: Web/Database
AutoReqProv: no
Requires: eGroupWare = %{version}-%{packaging}
%description %{filescenter}
This is the %{filescenter} app for eGroupWare.

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
This is the %{infolog} app for eGroupWare (Notes, ToDo, Phonelogs, CRM).

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
This is the %{manual} app for eGroupWare: online help system.

%package %{messenger}
Summary: The eGroupWare %{messenger} application
Group: Web/Database
AutoReqProv: no
Requires: eGroupWare = %{version}-%{packaging} 
%description %{messenger}
This is the %{messenger} app for eGroupWare.

%package %{mydms}
Summary: The eGroupWare %{mydms} application
Group: Web/Database
AutoReqProv: no
Requires: eGroupWare = %{version}-%{packaging} 
%description %{mydms}
This is a %{mydms} port to eGroupWare.

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

%package %{projectmanager}
Summary: The eGroupWare %{projectmanager} application
Group: Web/Database
AutoReqProv: no
Requires: eGroupWare = %{version}-%{packaging},
%description %{projectmanager}
The %{projectmanager} is eGroupWare's new project management application.
It's fully integrated into eGroupWare and use the data of InfoLog and Calendar.
Plugable datasources allow to support and manage further applications.

%package %{projects}
Summary: The eGroupWare %{projects} application
Group: Web/Database
AutoReqProv: no
Requires: eGroupWare = %{version}-%{packaging},
%description %{projects}
This is the %{projects} app for eGroupWare.

%package %{registration}
Summary: The eGroupWare %{registration} application
Group: Web/Database
AutoReqProv: no
Requires: eGroupWare = %{version}-%{packaging}
%description %{registration}
This is the %{registration} app for eGroupWare.

%package %{resources}
Summary: The eGroupWare %{resources} application
Group: Web/Database
AutoReqProv: no
Requires: eGroupWare = %{version}-%{packaging}
%description %{resources}
%{resources} is a resource booking sysmtem for eGroupWare.
Which integrates into the calendar.

%package %{sambaadmin}
Summary: The eGroupWare %{sambaadmin} application
Group: Web/Database
AutoReqProv: no
Requires: eGroupWare = %{version}-%{packaging}
%description %{sambaadmin}
Manage LDAP based sambaacounts and workstations.

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

%package %{syncml}
Summary: The eGroupWare %{syncml} application
Group: Web/Database
AutoReqProv: no
Requires: eGroupWare = %{version}-%{packaging}
%description %{syncml}
This is the %{syncml} app for eGroupWare.

%package %{timesheet}
Summary: The eGroupWare timesheet application
Group: Web/Database
AutoReqProv: no
Requires: eGroupWare = %{version}-%{packaging}
%description %{timesheet}
Simple timesheet application, which allow to record and report
the times and other expenses. It can be uses as well standalone 
as together with the ProjectManager application.

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
Requires: eGroupWare = %{version}-%{packaging},
%description %{wiki}
This is the %{wiki} app for eGroupWare.

%package %{workflow}
Summary: The eGroupWare %{workflow} application
Group: Web/Database
AutoReqProv: no
Requires: eGroupWare = %{version}-%{packaging},
%description %{workflow}
This is the %{workflow} app for eGroupWare.

%prep
%setup -n %{egwdirname}

%build
# no build required

%install
[ "%{buildroot}" != "/" ] && rm -rf %{buildroot}
mkdir -p $RPM_BUILD_ROOT%{prefix}/%{egwdirname}
cp -aRf * $RPM_BUILD_ROOT%{prefix}/%{egwdirname}
#cp -aRf * $RPM_BUILD_ROOT%{prefix}
rm -f $RPM_BUILD_ROOT%{prefix}/%{egwdirname}/.htaccess
#cp .htaccess $RPM_BUILD_ROOT%{prefix}/%{egwdirname}

%clean
[ "%{buildroot}" != "/" ] && rm -rf %{buildroot}

%post

                                                                                                                             
    echo "***************************************************"
    echo "* Attention: You must create the FILES directory  *"
    echo "* manually outside the document root of your      *"
    echo "* webserver:                                      *"
    echo "* eg. docroot: /var/www/html                      *"
    echo "*     FILES:   /var/www/egwfiles/                 *"
    echo "* Give the webserver the rights to read and write *"
    echo "* and no anonymous access to this folders         *"
    echo "* *************************************************"                                                
    echo "* Please secure you apache and add                *"
    echo "* the follow lines to you httpd.conf              *"
    echo "*                                                 *"
    echo "* <Directory /var/www/html/egroupware>            *"
    echo "*   <Files ~ "\.inc\.php$">                       *"
    echo "*      Order allow,deny                           *"
    echo "*      Deny from all                              *"
    echo "*    </Files>                                     *"
    echo "* </Directory>                                    *"
    echo "***************************************************"

%postun

%files
%defattr(0744,root,root)
%dir %{prefix}/%{egwdirname}
%{prefix}/%{egwdirname}/about.php
%{prefix}/%{egwdirname}/anon_wrapper.php
%{prefix}/%{egwdirname}/header.inc.php.template
%{prefix}/%{egwdirname}/.htaccess
%{prefix}/%{egwdirname}/index.php
%{prefix}/%{egwdirname}/login.php
%{prefix}/%{egwdirname}/logout.php
%{prefix}/%{egwdirname}/notify.php
%{prefix}/%{egwdirname}/notify_simple.php
%{prefix}/%{egwdirname}/notifyxml.php
%{prefix}/%{egwdirname}/redirect.php
%{prefix}/%{egwdirname}/rpc.php
%{prefix}/%{egwdirname}/set_box.php
%{prefix}/%{egwdirname}/soap.php
%{prefix}/%{egwdirname}/xajax.php
%{prefix}/%{egwdirname}/xmlrpc.php
%{prefix}/%{egwdirname}/CVS
%{prefix}/%{egwdirname}/admin
%{prefix}/%{egwdirname}/doc
%{prefix}/%{egwdirname}/etemplate
%{prefix}/%{egwdirname}/home
%{prefix}/%{egwdirname}/phpgwapi
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

%files %{browser}
%defattr(0744,root,root)
%{prefix}/%{egwdirname}/%{browser}

%files %{calendar}
%defattr(0744,root,root)
%{prefix}/%{egwdirname}/%{calendar}

%files %{chatty}
%defattr(0744,root,root)
%{prefix}/%{egwdirname}/%{chatty}

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

%files %{felamimail}
%defattr(0744,root,root)
%{prefix}/%{egwdirname}/%{felamimail}

%files %{filemanager}
%defattr(0744,root,root)
%{prefix}/%{egwdirname}/%{filemanager}

%files %{filescenter}
%defattr(0744,root,root)
%{prefix}/%{egwdirname}/%{filescenter}

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

%files %{mydms}
%defattr(0744,root,root)
%{prefix}/%{egwdirname}/%{mydms}

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

%files %{projectmanager}
%defattr(0744,root,root)
%{prefix}/%{egwdirname}/%{projectmanager}

%files %{projects}
%defattr(0744,root,root)
%{prefix}/%{egwdirname}/%{projects}

%files %{registration}
%defattr(0744,root,root)
%{prefix}/%{egwdirname}/%{registration}

%files %{resources}
%defattr(0744,root,root)
%{prefix}/%{egwdirname}/%{resources}

%files %{sambaadmin}
%defattr(0744,root,root)
%{prefix}/%{egwdirname}/%{sambaadmin}

%files %{sitemgr}
%defattr(0744,root,root)
%{prefix}/%{egwdirname}/%{sitemgr}

%files %{stocks}
%defattr(0744,root,root)
%{prefix}/%{egwdirname}/%{stocks}

%files %{syncml}
%defattr(0744,root,root)
%{prefix}/%{egwdirname}/%{syncml}

%files %{timesheet}
%defattr(0744,root,root)
%{prefix}/%{egwdirname}/%{timesheet}

%files %{tts}
%defattr(0744,root,root)
%{prefix}/%{egwdirname}/%{tts}

%files %{wiki}
%defattr(0744,root,root)
%{prefix}/%{egwdirname}/%{wiki}

%files %{workflow}
%defattr(0744,root,root)
%{prefix}/%{egwdirname}/%{workflow}

%changelog
* Sat Jan 22 2006 Lars Kneschke <lars@kneschke.de> 1.2RC6-1
- fixed calendar bugs
- fixed async service problems
- fixed mysql index length
- implemented WBXML decoder/encoder in php(based in Horde code)
- added php based nt/lanmanager password creation code

* Thu Dec 15 2005 Ralf Becker <RalfBecker@outdoor-training.de> 1.2RC5-1
- creation of new groups in LDAP working again
- no more negative id's in the account-table (auto column) itself, 
  as not all DBMS can deal with it (mapping is done in the class now)
- infolog list shows (optional) the times and can switch details on and off 
- projectmanager records and shows now the resources and details of the elements
- wiki is include in the linkage system now
- new instant messenger application chatty in contrib
- other bugfixes and translation updates

* Fri Dec 02 2005 Ralf Becker <RalfBecker@outdoor-training.de> 1.2RC4-1
- Bugfixes in Kalendar: Freetimesearch, disabled not working stuff under IE
- MyDMS install: boolean columns are now created correct under mysql4+5
- registration with email approval working again
- workflow and vfs/filemanager fixed to deal with negative group-ids
- setup: charset-conversation now via backup, deinstall & reinstall backup
- xmlrpc: fixes in calendar and infolog
- fixed several other bugs

* Mon Nov 28 2005 Ralf Becker <RalfBecker@outdoor-training.de> 1.2RC3-1
- fixed registration app, is not longer in contrib now
- fixed egroupware zip, which wrongly included the contrib stuff
- fixed several other bugs

* Fri Nov 25 2005 Ralf Becker <RalfBecker@outdoor-training.de> 1.2RC2-3
- fixed not working account creation
- fixed not working category creation in sitemgr

* Fri Nov 25 2005 Ralf Becker <RalfBecker@outdoor-training.de> 1.2RC2-2
- fixed bug which prefented installation under php4 of RC2.
- some minor bug-fixes happening this morning

* Thu Nov 24 2005 Ralf Becker <RalfBecker@outdoor-training.de> 1.2RC2-1
- calendar now fully supports groups as participatns and xmlrpc is working again
- group-id's are now negative to improve ldap support
- modified logo and look for the 1.2 idots template
- bugfixes in many areas

* Mon Nov 14 2005 Ralf Becker <RalfBecker@outdoor-training.de> 1.2RC1-1
- first release candidate of the upcomming 1.2 release:
- complete rewrite of the calendar, plus new resource booking system
- new projectmanager applications using infolog and calendar data
- syncml to synchronise cell-phones, PDA's and outlook
- workflow application
- and many more ...

* Tue Sep 20 2005 Ralf Becker <RalfBecker@outdoor-training.de> 1.0.0.009-3
- disabled the xmlrpc log again by default
- fixed addressbook bug introduced by a backported bugfix from HEAD

* Mon Sep 12 2005 Ralf Becker <RalfBecker@outdoor-training.de> 1.0.0.009-2
- further xmlrpc security fixes (already included in the tgz from mid Aug)
- xmlrpc and soap subsystem is now deactivated by default, it can be enabled
  via Admin >> site configuration if needed

* Fri Jul 16 2005 Ralf Becker <RalfBecker@outdoor-training.de> 1.0.0.008-2
- Fixed projects problem (editing of project not working, dates are allways 
  set to ~ 1970-01-01) introduced by security fix between 007 and 008

* Fri Jul 08 2005 Ralf Becker <RalfBecker@outdoor-training.de> 1.0.0.008-1
- Fixed xmlrpc security problems

* Sat Apr 15 2005 Ralf Becker <RalfBecker@outdoor-training.de> 1.0.0.007-2
- Fixed security problems reported by James from GulfTech Security Research
- new croation translations, significant enhancements in other languages
- many Bugfixes, see http://egroupware.org/cvschangelog-1.0/

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

