%define packagename phpGroupWare-all-apps
%define phpgwdirname phpgroupware
%define version 0.9.14.508
%define packaging 1
%define httpdroot  /var/www/html

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
phpGroupWare is a web-based groupware suite written in PHP. This package provides:

phpgroupware core app, addressbook, backup, bookmark, brewer, calendar, chat, chora (view cvs repository), comic, developer_tools, dj, doc, eldaptir, email, felamimail, forum, ftp, headlines hr (human resources), 
img (image editor), infolog (CRM), inv (inventory application), manual, meerkat (example XML-RPC application), messenger (internel message app), netsaint (configuration tool for netsaint network monitor), news_admin, nntp, phonelog, phpsysinfo, polls, projects (advanced project management), qmailldap, registration, sitemgr (web content manager), skel, soap, stocks, timetrack, todo, xmlrpc. 

It also provides an API for developing additional applications. See the phpgroupware
apps project for add-on apps.

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
%{prefix}/%{phpgwdirname}/addressbook
%{prefix}/%{phpgwdirname}/backup
%{prefix}/%{phpgwdirname}/bookmarks
%{prefix}/%{phpgwdirname}/brewer
%{prefix}/%{phpgwdirname}/calendar
%{prefix}/%{phpgwdirname}/chat
%{prefix}/%{phpgwdirname}/chora
%{prefix}/%{phpgwdirname}/comic
%{prefix}/%{phpgwdirname}/developer_tools
%{prefix}/%{phpgwdirname}/dj
%{prefix}/%{phpgwdirname}/eldaptir
%{prefix}/%{phpgwdirname}/email
%{prefix}/%{phpgwdirname}/etemplate
%{prefix}/%{phpgwdirname}/felamimail
%{prefix}/%{phpgwdirname}/filemanager
%{prefix}/%{phpgwdirname}/forum
%{prefix}/%{phpgwdirname}/ftp
%{prefix}/%{phpgwdirname}/headlines
%{prefix}/%{phpgwdirname}/hr
%{prefix}/%{phpgwdirname}/img
%{prefix}/%{phpgwdirname}/infolog
%{prefix}/%{phpgwdirname}/inv
%{prefix}/%{phpgwdirname}/manual
%{prefix}/%{phpgwdirname}/meerkat
%{prefix}/%{phpgwdirname}/messenger
%{prefix}/%{phpgwdirname}/netsaint
%{prefix}/%{phpgwdirname}/news_admin
%{prefix}/%{phpgwdirname}/nntp
%{prefix}/%{phpgwdirname}/notes
%{prefix}/%{phpgwdirname}/phpbrain
%{prefix}/%{phpgwdirname}/phonelog
%{prefix}/%{phpgwdirname}/phpsysinfo
%{prefix}/%{phpgwdirname}/polls
%{prefix}/%{phpgwdirname}/projects
%{prefix}/%{phpgwdirname}/property
%{prefix}/%{phpgwdirname}/qmailldap
%{prefix}/%{phpgwdirname}/registration
%{prefix}/%{phpgwdirname}/sitemgr
%{prefix}/%{phpgwdirname}/skel
%{prefix}/%{phpgwdirname}/soap.php
%{prefix}/%{phpgwdirname}/soap
%{prefix}/%{phpgwdirname}/stocks
%{prefix}/%{phpgwdirname}/timetrack
%{prefix}/%{phpgwdirname}/todo
%{prefix}/%{phpgwdirname}/tts
%{prefix}/%{phpgwdirname}/wiki
%{prefix}/%{phpgwdirname}/xmlrpc.php
%{prefix}/%{phpgwdirname}/xmlrpc

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

* Sat Dec 28 2002 Reiner Jung <phpgroupware-developers@gnu.org> 0.9.14.001
- Build from first all application based rpm
- This is the bugfix release of phpGroupWare
- Packackes LSB compliant. Not tested on United Linux (SuSE, SCO ..).
- Add depencies to the rpm packages
- Include package admin, setup,phpgwapi and preferences to the base package
- rpm change to install folder /var/www/html for RedHat install
- Apache 2 support
- Ported Anglemail back as the default email app
- Improved LDAP support
- Added additional translations
- Offical added sitemgr to the .14 branch with multilingual support
- Various fixes for most apps

# end of file
