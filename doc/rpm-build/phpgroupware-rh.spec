%define packagename phpGroupWare
%define phpgwdirname phpgroupware
%define version 0.9.14.508
%define packaging 1
%define httpdroot  /var/www/html

%define addressbook addressbook
%define admin admin
%define backup backup
%define bookmarks bookmarks
%define brewer brewer
%define calendar calendar
%define cart cart
%define chat chat
%define chora chora
%define comic comic
%define developer_tools developer_tools
%define discjockey dj
%define docs doc
%define eldaptir eldaptir
%define email email
%define felamimail felamimail
%define etemplate etemplate
%define filemanager filemanager
%define forum forum
%define ftp ftp
%define headlines headlines
%define human_resources hr
%define img img
%define infolog infolog
%define inv inv
%define manual manual
%define meerkat meerkat
%define messenger messenger
%define netsaint netsaint
%define news_admin news_admin
%define nntp nntp
%define notes notes
%define phonelog phonelog
%define phpbrain phpbrain
%define phpgwapi phpgwapi
%define phpsysinfo phpsysinfo
%define polls polls
%define preferences preferences
%define projects projects
%define property property
%define qmailldap qmailldap
%define registration registration
%define setup_module setup
%define sitemgr sitemgr
%define skel skel
%define soap soap
%define stocks stocks
%define timetrack timetrack
%define todo todo
%define tts tts
%define wap wap
%define wcm wcm
%define weather weather
%define wiki wiki
%define xmlrpc xmlrpc

Summary: phpGroupWare is a web-based groupware suite written in php. 
Name: %{packagename}
Version: %{version}
Release: %{packaging}
Copyright: GPL
Group: Web/Database
URL: http://www.phpgroupware.org/
Source: phpgroupware-%{version}.tar.bz2
BuildRoot: /tmp/%{packagename}-buildroot
Prefix: %{httpdroot}
Vendor: phpGroupWare
Packager: phpGroupWare <rpm@phpgroupware.org>
Buildarch: noarch
AutoReqProv: no
Requires: php >= 4.0.6
%description
phpGroupWare is a web-based groupware suite written in PHP. 
The core package provides the admin, setup, phpgwapi and preferences
packages. It also provides an API for developing additional applications. 
See the phpgroupware apps project for add-on apps.

%package %{addressbook}
Summary: The phpGroupWare %{addressbook} application
Group: Web/Database
AutoReqProv: no
Requires: phpGroupWare = %{version}-%{packaging}
%description %{addressbook}
Contact manager with Vcard support.
%{addressbook} is the phpgroupware default contact application.
It makes use of the phpgroupware contacts class to store and retrieve 
contact information via SQL or LDAP.

%package %{backup}
Summary: The phpGroupWare %{backup} application
Group: Web/Database
AutoReqProv: no
Requires: phpGroupWare = %{version}-%{packaging}
%description %{backup}
An online configurable backup app to store data offline. 
Can store files in zip, tar.gz and tar.bz2 on the local machine 
or Remote via FTP, SMBMOUNT or NFS 

%package %{bookmarks}
Summary: The phpGroupWare %{bookmarks} application
Group: Web/Database
AutoReqProv: no
Requires: phpGroupWare = %{version}-%{packaging}
%description %{bookmarks}
Manage your bookmarks with phpGW. Has Netscape plugin.

%package %{brewer}
Summary: The phpGroupWare %{brewer} application
Group: Web/Database
AutoReqProv: no
Requires: phpGroupWare = %{version}-%{packaging}
%description %{brewer}
Manages your home-brew recipes.

%package %{calendar}
Summary: The phpGroupWare %{calendar} application
Group: Web/Database
AutoReqProv: no
Requires: phpGroupWare = %{version}-%{packaging}
%description %{calendar}
Powerful calendar with meeting request system, ICal and E-Mail support, 
and ACL security.

%package %{chat}
Summary: The phpGroupWare %{chat} application
Group: Web/Database
AutoReqProv: no
Requires: phpGroupWare = %{version}-%{packaging}
%description %{chat}
This is the %{chat} app for phpGroupWare.

%package %{chora}
Summary: The phpGroupWare %{chora} application
Group: Web/Database
AutoReqProv: no
Requires: phpGroupWare = %{version}-%{packaging}
%description %{chora}
This is the %{chora} app for phpGroupWare.

%package %{comic}
Summary: The phpGroupWare %{comic} application
Group: Web/Database
AutoReqProv: no
Requires: phpGroupWare = %{version}-%{packaging}
%description %{comic}
This application display comic strips.

%package %{developer_tools}
Summary: The phpGroupWare %{developer_tools} application
Group: Web/Database
AutoReqProv: no
Requires: phpGroupWare = %{version}-%{packaging}
%description %{developer_tools}
The TranslationTools allow to create and extend translations-files for phpGroupWare. They can search the sources for new / added phrases and show you the ones missing in your language. 

%package %{discjockey}
Summary: The phpGroupWare %{discjockey} application
Group: Web/Database
AutoReqProv: no
Requires: phpGroupWare = %{version}-%{packaging}
%description %{discjockey}
This is the %{discjockey} app for phpGroupWare.

%package %{docs}
Summary: The phpGroupWare %{docs}
Group: Web/Database
AutoReqProv: no
Requires: phpGroupWare = %{version}-%{packaging}
%description %{docs}
This is the %{docs} for phpGroupWare.

%package %{eldaptir}
Summary: The phpGroupWare %{eldaptir} application
Group: Web/Database
AutoReqProv: no
Requires: phpGroupWare = %{version}-%{packaging}
%description %{eldaptir}
This is the LDAP browser application for phpGroupWare.

%package %{email}
Summary: The phpGroupWare %{email} application
Group: Web/Database
AutoReqProv: no
Requires: phpGroupWare = %{version}-%{packaging}, phpGroupWare-addressbook = %{version}-%{packaging}
%description %{email}
AngleMail for phpGroupWare at www.anglemail.org is an Email reader with multiple accounts and mailbox filtering. .

%package %{etemplate}
Summary: The phpGroupWare %{etempalte} application
Group: Web/Database
AutoReqProv: no
Requires: phpGroupWare = %{version}-%{packaging}, phpGroupWare-addressbook = %{version}-%{packaging}
%description %{etemplate}
eTemplates are a new widget-based template system for phpGroupWare with an interactive editor and a database table-editor (creates tables_current.inc.php and updates automaticaly tables_update.inc.php)

%package %{felamimail}
Summary: The phpGroupWare %{felamimail} application
Group: Web/Database
AutoReqProv: no
Requires: phpGroupWare = %{version}-%{packaging}
%description %{felamimail}
This is the %{felamimail} app for phpGroupWare.

%package %{filemanager}
Summary: The phpGroupWare %{filemanager} application
Group: Web/Database
AutoReqProv: no
Requires: phpGroupWare = %{version}-%{packaging}
%description %{filemanager}
This is the %{filemanager} app for phpGroupWare.

%package %{forum}
Summary: The phpGroupWare %{forum} application
Group: Web/Database
AutoReqProv: no
Requires: phpGroupWare = %{version}-%{packaging}
%description %{forum}
This is the %{forum} app for phpGroupWare.

%package %{ftp}
Summary: The phpGroupWare %{ftp} application
Group: Web/Database
AutoReqProv: no
Requires: phpGroupWare = %{version}-%{packaging} 
%description %{ftp}
This is the %{ftp} app for phpGroupWare.

%package %{headlines}
Summary: The phpGroupWare %{headlines} application
Group: Web/Database
AutoReqProv: no
Requires: phpGroupWare = %{version}-%{packaging} 
%description %{headlines}
This is the %{headlines} app for phpGroupWare.

%package %{human_resources}
Summary: The phpGroupWare %{human_resources} application
Group: Web/Database
AutoReqProv: no
Requires: phpGroupWare = %{version}-%{packaging} 
%description %{human_resources}
This is the %{human_resources} app for phpGroupWare.

%package %{img}
Summary: The phpGroupWare %{img} application
Group: Web/Database
AutoReqProv: no
Requires: phpGroupWare = %{version}-%{packaging}, gd
%description %{img}
This is the %{img} app for phpGroupWare.

%package %{infolog}
Summary: The phpGroupWare %{infolog} application
Group: Web/Database
AutoReqProv: no
Requires: phpGroupWare = %{version}-%{packaging} 
%description %{infolog}
This is the %{infolog} app for phpGroupWare.

%package %{inv}
Summary: The phpGroupWare %{inv} application
Group: Web/Database
AutoReqProv: no
Requires: phpGroupWare = %{version}-%{packaging}, phpGroupWare-addressbook = %{version}-%{packaging}
%description %{inv}
This is the %{inv} app for phpGroupWare.

%package %{manual}
Summary: The phpGroupWare %{manual} application
Group: Web/Database
AutoReqProv: no
Requires: phpGroupWare = %{version}-%{packaging}
%description %{manual}
This is the %{manual} app for phpGroupWare.

%package %{meerkat}
Summary: The phpGroupWare %{meerkat} application
Group: Web/Database
AutoReqProv: no
AutoReqProv: no
Requires: phpGroupWare = %{version}-%{packaging} 
%description %{meerkat}
This is the %{meerkat} app for phpGroupWare.

%package %{messenger}
Summary: The phpGroupWare %{messenger} application
Group: Web/Database
AutoReqProv: no
Requires: phpGroupWare = %{version}-%{packaging} 
%description %{messenger}
This is the %{messenger} app for phpGroupWare.

%package %{netsaint}
Summary: The phpGroupWare %{netsaint} application
Group: Web/Database
AutoReqProv: no
Requires: phpGroupWare = %{version}-%{packaging}, phpGroupWare-addressbook = %{version}-%{packaging}
%description %{netsaint}
This is the %{netsaint} app for phpGroupWare.

%package %{news_admin}
Summary: The phpGroupWare %{news_admin} application
Group: Web/Database
AutoReqProv: no
Requires: phpGroupWare = %{version}-%{packaging} 
%description %{news_admin}
This is the %{news_admin} app for phpGroupWare.

%package %{nntp}
Summary: The phpGroupWare %{nntp} application
Group: Web/Database
AutoReqProv: no
Requires: phpGroupWare = %{version}-%{packaging}
%description %{nntp}
This is the %{nntp} app for phpGroupWare.

%package %{notes}
Summary: The phpGroupWare %{notes} application
Group: Web/Database
AutoReqProv: no
Requires: phpGroupWare = %{version}-%{packaging}
%description %{notes}
This is the %{notes} app for phpGroupWare.

%package %{phonelog}
Summary: The phpGroupWare %{phonelog} application
Group: Web/Database
AutoReqProv: no
Requires: phpGroupWare = %{version}-%{packaging}
%description %{phonelog}
This is the %{phonelog} app for phpGroupWare.

%package %{phpbrain}
Summary: The phpGroupWare %{phpbrain} application
Group: Web/Database
AutoReqProv: no
Requires: phpGroupWare = %{version}-%{packaging}, phpGroupWare-addressbook = %{version}-%{packaging}
%description %{phpbrain}
This is the %{phpbrain} app for phpGroupWare.

%package %{phpsysinfo}
Summary: The phpGroupWare %{phpsysinfo} application
Group: Web/Database
AutoReqProv: no
Requires: phpGroupWare = %{version}-%{packaging}
%description %{phpsysinfo}
This is the cire %{phpsysinfo} of phpGroupWare.

%package %{polls}
Summary: The phpGroupWare %{polls} application
Group: Web/Database
AutoReqProv: no
Requires: phpGroupWare = %{version}-%{packaging}
%description %{polls}
This is the %{polls} app for phpGroupWare.

%package %{projects}
Summary: The phpGroupWare %{projects} application
Group: Web/Database
AutoReqProv: no
Requires: phpGroupWare = %{version}-%{packaging}, phpGroupWare-addressbook = %{version}-%{packaging}
%description %{projects}
This is the %{projects} app for phpGroupWare.

%package %{property}
Summary: The phpGroupWare %{property} application
Group: Web/Database
AutoReqProv: no
Requires: phpGroupWare = %{version}-%{packaging}, phpGroupWare-addressbook = %{version}-%{packaging}
%description %{property}
This is the %{property} app for phpGroupWare.

%package %{qmailldap}
Summary: The phpGroupWare %{qmailldap} application
Group: Web/Database
AutoReqProv: no
Requires: phpGroupWare = %{version}-%{packaging}
%description %{qmailldap}
This is the %{qmailldap} app for phpGroupWare.

%package %{registration}
Summary: The phpGroupWare %{registration} application
Group: Web/Database
AutoReqProv: no
Requires: phpGroupWare = %{version}-%{packaging}
%description %{registration}
This is the %{registration} app for phpGroupWare.

%package %{sitemgr}
Summary: The phpGroupWare %{sitemgr} application
Group: Web/Database
AutoReqProv: no
Requires: phpGroupWare = %{version}-%{packaging}
%description %{sitemgr}
This is the %{sitemgr} app for phpGroupWare.

%package %{skel}
Summary: The phpGroupWare %{skel} application
Group: Web/Database
AutoReqProv: no
Requires: phpGroupWare = %{version}-%{packaging}, phpGroupWare-email = %{version}-%{packaging}
%description %{skel}
This is the %{skel} app for phpGroupWare.

%package %{soap}
Summary: The phpGroupWare %{soap} application
Group: Web/Database
AutoReqProv: no
Requires: phpGroupWare = %{version}-%{packaging}
%description %{soap}
This is the %{soap} app for phpGroupWare.

%package %{stocks}
Summary: The phpGroupWare %{stocks} application
Group: Web/Database
AutoReqProv: no
Requires: phpGroupWare = %{version}-%{packaging}
%description %{stocks}
This is the %{stocks} app for phpGroupWare.

%package %{timetrack}
Summary: The phpGroupWare %{timetrack} application
Group: Web/Database
AutoReqProv: no
Requires: phpGroupWare = %{version}-%{packaging}
%description %{timetrack}
This is the %{timetrack} app for phpGroupWare.

%package %{todo}
Summary: The phpGroupWare %{todo} application
Group: Web/Database
AutoReqProv: no
Requires: phpGroupWare = %{version}-%{packaging}
%description %{todo}
This is the %{todo} app for phpGroupWare.

%package %{tts}
Summary: The phpGroupWare %{tts} application
Group: Web/Database
AutoReqProv: no
Requires: phpGroupWare = %{version}-%{packaging}
%description %{tts}
This is the %{tts} app for phpGroupWare.


%package %{wiki}
Summary: The phpGroupWare %{wiki} application
Group: Web/Database
AutoReqProv: no
Requires: phpGroupWare = %{version}-%{packaging}, phpGroupWare-addressbook = %{version}-%{packaging}
%description %{wiki}
This is the %{wiki} app for phpGroupWare.

%package %{xmlrpc}
Summary: The phpGroupWare %{xmlrpc} application
Group: Web/Database
AutoReqProv: no
Requires: phpGroupWare = %{version}-%{packaging}
%description %{xmlrpc}
This is the %{xmlrpc} app for phpGroupWare.

%prep
%setup -n %{phpgwdirname}

%build
# no build required

%install
rm -rf $RPM_BUILD_ROOT
mkdir -p $RPM_BUILD_ROOT%{prefix}/%{phpgwdirname}
cp -aRf * $RPM_BUILD_ROOT%{prefix}/%{phpgwdirname}
#mkdir -p $RPM_BUILD_ROOT%{prefix}/%{phpgwdirname}/files/home
#mkdir -p $RPM_BUILD_ROOT%{prefix}/%{phpgwdirname}/files/groups
#mkdir -p $RPM_BUILD_ROOT%{prefix}/%{phpgwdirname}/files/users

%clean
rm -rf $RPM_BUILD_ROOT

%post

%postun

%files
#%attr(0770,apache,apache) %{prefix}/%{phpgwdirname}/files/groups
#%attr(0770,apache,apache) %{prefix}/%{phpgwdirname}/files/users
#%attr(0770,apache,apache) %{prefix}/%{phpgwdirname}/files/home
%defattr(-,root,root)
%dir %{prefix}/%{phpgwdirname}
%{prefix}/%{phpgwdirname}/home.php
%{prefix}/%{phpgwdirname}/about.php
%{prefix}/%{phpgwdirname}/anon_wrapper.php
%{prefix}/%{phpgwdirname}/notify.php
%{prefix}/%{phpgwdirname}/notify_simple.php
%{prefix}/%{phpgwdirname}/redirect.php
%{prefix}/%{phpgwdirname}/set_box.php
%{prefix}/%{phpgwdirname}/header.inc.php.template
%{prefix}/%{phpgwdirname}/version.inc.php
%{prefix}/%{phpgwdirname}/index.php
%{prefix}/%{phpgwdirname}/login.php
%{prefix}/%{phpgwdirname}/logout.php
%{prefix}/%{phpgwdirname}/CVS
%{prefix}/%{phpgwdirname}/doc
%{prefix}/%{phpgwdirname}/phpgwapi
%{prefix}/%{phpgwdirname}/admin
%{prefix}/%{phpgwdirname}/preferences
%{prefix}/%{phpgwdirname}/setup
#%{prefix}/%{phpgwdirname}/files

%files %{addressbook}
%defattr(-,root,root)
%{prefix}/%{phpgwdirname}/%{addressbook}

%files %{backup}
%defattr(-,root,root)
%{prefix}/%{phpgwdirname}/%{backup}

%files %{bookmarks}
%defattr(-,root,root)
%{prefix}/%{phpgwdirname}/%{bookmarks}

%files %{brewer}
%defattr(-,root,root)
%{prefix}/%{phpgwdirname}/%{brewer}

%files %{calendar}
%defattr(-,root,root)
%{prefix}/%{phpgwdirname}/%{calendar}

%files %{chat}
%defattr(-,root,root)
%{prefix}/%{phpgwdirname}/%{chat}

%files %{chora}
%defattr(-,root,root)
%{prefix}/%{phpgwdirname}/%{chora}

%files %{comic}
%defattr(-,root,root)
%{prefix}/%{phpgwdirname}/%{comic}

%files %{developer_tools}
%defattr(-,root,root)
%{prefix}/%{phpgwdirname}/%{developer_tools}

%files %{discjockey}
%{prefix}/%{phpgwdirname}/%{discjockey}

%files %{docs}
%defattr(-,root,root)
%{prefix}/%{phpgwdirname}/phpgwapi/%{docs}

%files %{eldaptir}
%defattr(-,root,root)
%{prefix}/%{phpgwdirname}/%{eldaptir}

%files %{email}
%defattr(-,root,root)
%{prefix}/%{phpgwdirname}/%{email}

%files %{etemplate}
%defattr(-,root,root)
%{prefix}/%{phpgwdirname}/%{etemplate}

%files %{felamimail}
%defattr(-,root,root)
%{prefix}/%{phpgwdirname}/%{felamimail}

%files %{filemanager}
%defattr(-,root,root)
%{prefix}/%{phpgwdirname}/%{filemanager}

%files %{forum}
%defattr(-,root,root)
%{prefix}/%{phpgwdirname}/%{forum}

%files %{ftp}
%defattr(-,root,root)
%{prefix}/%{phpgwdirname}/%{ftp}

%files %{headlines}
%defattr(-,root,root)
%{prefix}/%{phpgwdirname}/%{headlines}

%files %{human_resources}
%defattr(-,root,root)
%{prefix}/%{phpgwdirname}/%{human_resources}

%files %{img}
%defattr(-,root,root)
%{prefix}/%{phpgwdirname}/%{img}

%files %{infolog}
%defattr(-,root,root)
%{prefix}/%{phpgwdirname}/%{infolog}

%files %{inv}
%defattr(-,root,root)
%{prefix}/%{phpgwdirname}/%{inv}

%files %{manual}
%defattr(-,root,root)
%{prefix}/%{phpgwdirname}/%{manual}

%files %{meerkat}
%defattr(-,root,root)
%{prefix}/%{phpgwdirname}/%{meerkat}

%files %{messenger}
%defattr(-,root,root)
%{prefix}/%{phpgwdirname}/%{messenger}

%files %{netsaint}
%defattr(-,root,root)
%{prefix}/%{phpgwdirname}/%{netsaint}

%files %{news_admin}
%defattr(-,root,root)
%{prefix}/%{phpgwdirname}/%{news_admin}

%files %{nntp}
%defattr(-,root,root)
%{prefix}/%{phpgwdirname}/%{nntp}

%files %{notes}
%defattr(-,root,root)
%{prefix}/%{phpgwdirname}/%{notes}

%files %{phonelog}
%defattr(-,root,root)
%{prefix}/%{phpgwdirname}/%{phonelog}

%files %{phpbrain}
%defattr(-,root,root)
%{prefix}/%{phpgwdirname}/%{phpbrain}

%files %{phpsysinfo}
%defattr(-,root,root)
%{prefix}/%{phpgwdirname}/%{phpsysinfo}

%files %{polls}
%defattr(-,root,root)
%{prefix}/%{phpgwdirname}/%{polls}

%files %{projects}
%defattr(-,root,root)
%{prefix}/%{phpgwdirname}/%{projects}

%files %{property}
%defattr(-,root,root)
%{prefix}/%{phpgwdirname}/%{property}

%files %{qmailldap}
%defattr(-,root,root)
%{prefix}/%{phpgwdirname}/%{qmailldap}

%files %{registration}
%defattr(-,root,root)
%{prefix}/%{phpgwdirname}/%{registration}

%files %{sitemgr}
%defattr(-,root,root)
%{prefix}/%{phpgwdirname}/%{sitemgr}

%files %{skel}
%defattr(-,root,root)
%{prefix}/%{phpgwdirname}/%{skel}

%files %{soap}
%defattr(-,root,root)
%{prefix}/%{phpgwdirname}/soap.php
%{prefix}/%{phpgwdirname}/%{soap}

%files %{stocks}
%defattr(-,root,root)
%{prefix}/%{phpgwdirname}/%{stocks}

%files %{timetrack}
%defattr(-,root,root)
%{prefix}/%{phpgwdirname}/%{timetrack}

%files %{todo}
%defattr(-,root,root)
%{prefix}/%{phpgwdirname}/%{todo}

%files %{tts}
%defattr(-,root,root)
%{prefix}/%{phpgwdirname}/%{tts}

%files %{wiki}
%defattr(-,root,root)
%{prefix}/%{phpgwdirname}/%{wiki}

%files %{xmlrpc}
%defattr(-,root,root)
%{prefix}/%{phpgwdirname}/xmlrpc.php
%{prefix}/%{phpgwdirname}/%{xmlrpc}

%changelog
* Sat Jul 05 2003 Reiner Jung <r.jung@creativix.net> 0.9.14.005
- fix a typo error in the 005 packages.
                                                                                                                             
* Sat Jul 05 2003 Reiner Jung <r.jung@creativix.net> 0.9.14.005
- security release update for users from php3.
                                                                                                                             
* Thu Jul 03 2003 Reiner Jung <r.jung@creativix.net> 0.9.14.004
- Security Release for phpGroupWare.
- bugfix for XSS exploit
- Vfs move out of the webserver Root

* Fri Apr 18 2003 Reiner Jung <r.jung@creativix.net> 0.9.14.003
- RPM depecies bug fixed
- calendar, day view fixed display prob.
- bookmarks,img,inv,netsaint no labels in ACL
- loging in or calling the welcome page displays a "available Memory exhausted" error fixed
- postgreSql and language Setup

* Fri Mar 28 2003 Reiner Jung <r.jung@creativix.net> 0.9.14.002
- Bufix for user management

* Sun Mar 23 2003 Reiner Jung <r.jung@creativix.net> 0.9.14.002
- bugfix release

* Fri Dec 27 2002 Reiner Jung <phpgroupware-developers@gnu.org> 0.9.14.001
- Packackes LSB compliant. Not tested on United Linux (SuSE, SCO ..).
- Add depencies to the rpm packages
- Include package admin, setup,phpgwapi and preferences to the base package

* Fri Dec 20 2002 Reiner Jung <phpgroupware-developers@gnu.org> 0.9.14.001
- This is the bugfix release of phpGroupWare
- rpm change to install folder /var/www/html for RedHat install
- Apache 2 support
- Ported Anglemail back as the default email app
- Improved LDAP support
- Added additional translations
- Offical added sitemgr to the .14 branch with multilingual support
- Various fixes for most apps

* Sun Aug 25 2002 Mark Peters <skeeter@phpgroupware.org> 0.9.14.000
- This is the official release of phpGroupWare 0.9.14.000
* Fri Apr 14 2002 Mark Peters <skeeter@phpgroupware.org> 0.9.14.RC3
- Added the BLANK files to the files directory.
- Package relocation is more flexible.
- Added timetrack as a working app.

* Sun Mar 03 2002 Mark Peters <skeeter@phpgroupware.org> 0.9.14.RC2

* Fri Jan 13 2002 Mark Peters <skeeter@phpgroupware.org> 0.9.14.RC1

* Fri Nov 16 2001 Mark Peters <skeeter@phpgroupware.org> 0.9.12.001
- Created subpackaging in the RPM spec file
- Upgraded to new 0.9.12.001 version.
- Reconfigured package name.
- Defined serial version numbers to allow subapps to have dependencies

* Sat Jan 6 2001 Dan Kuykendall <seek3r@phpgroupware.org> 0.9.9
- Upgraded to new 0.9.8 version.
- Removed lots of unneeded code that was needed for the pre-beta versions.
- Added support for RedHat and Mandrake distro's.
- General clean up so that this can be reused by the project

* Sat Sep 16 2000 Geoffrey Lee <snailtalk@mandrakesoft.com> 09072000-2mdk
- Add url.
- turn off autorequires.
- use /var/www.

* Wed Sep 13 2000  <snailtalk@mandrakesoft.com> 09072000-1mdk
- first rpm-zed distribution.
- cutom configuration files from Dan Kuykendall.
- suggestions on packaging from Dan.

# end of file
