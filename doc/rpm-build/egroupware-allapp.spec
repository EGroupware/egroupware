%define packagename eGroupWare-all-apps
%define egwdirname egroupware
%define version 0.9.99.014
%define packaging 1
%define epoch 0
%define httpdroot  %(if test -f /etc/SuSE-release; then echo /srv/www/htdocs; else echo /var/www/html; fi)

Name: %{packagename}
Version: %{version}
Release: %{packaging}
Epoch: %{epoch}
Summary: eGroupWare is a web-based groupware suite written in php.
                                                                                                                             
Group: Web/Database
License: GPL/LGPL
URL: http://www.egroupware.org/
Source0:  http://download.sourceforge.net/egroupware/eGroupWare-%{version}-%{packaging}.tar.bz2
BuildRoot: /tmp/%{packagename}-buildroot
Requires: php >= 4.0.6
                                                                                                                             
Prefix: %{httpdroot}
Buildarch: noarch
AutoReqProv: no
                                                                                                                             
Vendor: eGroupWare
Packager: eGroupWare <r.jung@creativix.net>

%description
eGroupWare is a web-based groupware suite written in PHP. This package provides:

egroupware core app, addressbook, backup, bookmarks, calendar, comic, developer tools, 
docs, email, emailadmin, etemplate, felamimail, filemanager, forum, ftp, fudforum, 
headlines, infolog, jinn, messenger news admin, phpldapadmin, phpbrain (knowledgebase), 
phpsysinfo, polls, projects (advanced project management), registration, sitemgr, 
skel, stocks, tts (trouble ticket system), wiki

It also provides an API for developing additional applications. See the egroupware
apps project for add-on apps.

%prep
%setup -n %{egwdirname}

%build

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
    echo "*   <Files ~ "\.inc\.php$|.tpl$">                 *"
    echo "*      Order allow,deny                           *"
    echo "*      Deny from all                              *"
    echo "*    </Files>                                     *"
    echo "***************************************************"

%postun

%files
%defattr(-,root,root)
%dir %{prefix}/%{egwdirname}
%{prefix}/%{egwdirname}/home.php
%{prefix}/%{egwdirname}/about.php
%{prefix}/%{egwdirname}/anon_wrapper.php
%{prefix}/%{egwdirname}/notify.php
%{prefix}/%{egwdirname}/notify_simple.php
%{prefix}/%{egwdirname}/redirect.php
%{prefix}/%{egwdirname}/set_box.php
%{prefix}/%{egwdirname}/header.inc.php.template
%{prefix}/%{egwdirname}/index.php
%{prefix}/%{egwdirname}/soap.php
%{prefix}/%{egwdirname}/xmlrpc.php
%{prefix}/%{egwdirname}/xmlrpc.php.old
%{prefix}/%{egwdirname}/login.php
%{prefix}/%{egwdirname}/logout.php
%{prefix}/%{egwdirname}/CVS
%{prefix}/%{egwdirname}/doc
%{prefix}/%{egwdirname}/phpgwapi
%{prefix}/%{egwdirname}/admin
%{prefix}/%{egwdirname}/preferences
%{prefix}/%{egwdirname}/setup
%{prefix}/%{egwdirname}/addressbook
%{prefix}/%{egwdirname}/backup
%{prefix}/%{egwdirname}/bookmarks
%{prefix}/%{egwdirname}/calendar
%{prefix}/%{egwdirname}/comic
%{prefix}/%{egwdirname}/developer_tools
%{prefix}/%{egwdirname}/email
%{prefix}/%{egwdirname}/emailadmin
%{prefix}/%{egwdirname}/etemplate
%{prefix}/%{egwdirname}/felamimail
%{prefix}/%{egwdirname}/filemanager
%{prefix}/%{egwdirname}/forum
%{prefix}/%{egwdirname}/ftp
%{prefix}/%{egwdirname}/fudforum
%{prefix}/%{egwdirname}/headlines
%{prefix}/%{egwdirname}/infolog
%{prefix}/%{egwdirname}/jinn
%{prefix}/%{egwdirname}/messenger
%{prefix}/%{egwdirname}/news_admin
%{prefix}/%{egwdirname}/phpbrain
%{prefix}/%{egwdirname}/phpldapadmin
%{prefix}/%{egwdirname}/phpsysinfo
%{prefix}/%{egwdirname}/polls
%{prefix}/%{egwdirname}/projects
%{prefix}/%{egwdirname}/registration
%{prefix}/%{egwdirname}/sitemgr
%{prefix}/%{egwdirname}/skel
%{prefix}/%{egwdirname}/stocks
%{prefix}/%{egwdirname}/tts
%{prefix}/%{egwdirname}/wiki

%changelog
* Wed Mar 03 2004 Reiner Jung <r.jung@creativix.net> 0.9.99.014-1
- add support to spec file for SuSE directory structure
- extensions to Danish language
- extensions at sitemgr
- bugfixes for upcomming 1.0 release

* Sat Feb 07 2004 Reiner Jung <r.jung@creativix.net> 0.9.99.013-2
- RC3-4 bugfix for broken calender ACL

* Sat Feb 07 2004 Reiner Jung <r.jung@creativix.net> 0.9.99.013-1
- Release RC3-3 is only a small bugfixing for some installations
- PostgreSQL bug fixed
- Email Bug fixed
- Login problem on some clients fixed

* Wed Jan 28 2004 Reiner Jung <r.jung@creativix.net> 0.9.99.012-2
- We use the download problem at out server buf fix some other problems

* Wed Jan 28 2004 Reiner Jung <r.jung@creativix.net> 0.9.99.012
- remove justweb template
- Skel app added as package
- Messenger back in eGW
- Spanish translation finished
- Ukrain translation 50% finished
- extensions on Italian translation
- backup rewrite
- Poll upp is rewrited
- Knowledge Base rewrite (start from new killer app support center)
- sitemgr fist preview of 1.0 candidate
- extension on idots
- new template set included jerryr (preview to 1.0 version)
- felamimail extension (folders)
- email bugfixes and extensions
- username case sensitive
- encrytion from passwords for header.inc.php and database passwords added
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
- fudforum updated
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
- Experimental, internal usage from UTF-8 available
- Projects app extendet and 1st preview from gant charts available
- Simplified Chinese translation added
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
- initial eGroupWare package anouncement.

