%define packagename eGroupWare
%define egwdirname egroupware
%define version 1.2RC7
%define packaging 1
%define epoch 0
%define httpdroot  /var/www/html
%define httpdconfd  /etc/httpd/conf.d

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
Source0: http://download.sourceforge.net/egroupware/%{packagename}-%{version}-%{packaging}.tar.bz2
Source1: http://download.sourceforge.net/egroupware/%{packagename}-contrib-%{version}-%{packaging}.tar.bz2
Source2: egroupware_fedora.tar.bz2
Patch0: manageheader.php.patch
Patch1: class.uiasyncservice.inc.php.patch
BuildRoot: /tmp/%{packagename}-buildroot
Requires: php >= 4.3 php-mbstring >= 4.3 pear-log => 1.9.3 php-imap php-gd vixie-cron
Provides: egw-core egw-%{addressbook} egw-%{etemplate}
Conflicts: %{packagename}-core %{packagename}-%{addressbook} %{packagename}-%{bookmarks} %{packagename}-%{calendar} %{packagename}-%{developer_tools} %{packagename}-%{emailadmin} %{packagename}-%{felamimail} %{packagename}-%{filemanager} %{packagename}-%{infolog} %{packagename}-%{manual} %{packagename}-%{mydms} %{packagename}-%{news_admin} %{packagename}-%{phpbrain} %{packagename}-%{polls} %{packagename}-%{projectmanager} %{packagename}-%{registration} %{packagename}-%{resources} %{packagename}-%{sambaadmin} %{packagename}-%{sitemgr} %{packagename}-%{syncml} %{packagename}-%{timesheet} %{packagename}-%{wiki}
                                                                                                                             
Prefix: /usr/share
Buildarch: noarch
AutoReqProv: no
                                                                                                                             
Vendor: eGroupWare
Packager: Lars Kneschke <l.kneschke@metaways.de>

%description
eGroupWare is a web-based groupware suite written in PHP. 

This package provides the eGroupWare default applications:

egroupware core with: admin, api, docs, etemplate, prefereces and setup, 
addressbook, bookmarks, calendar, translation-tools, emailadmin, felamimail, 
filemanager, infolog, manual, mydms, news admin, knowledgebase, polls, 
projectmanager, resources, sambaadmin, sitemgr, syncml, timesheet, wiki, workflow

It also provides an API for developing additional applications. 

Further contributed applications are avalible in single packages.

#%package contrib
#Summary: The eGroupWare contrib package
#Group: Web/Database
#Requires: %{packagename}-core = %{version}-%{packaging}
#%description contrib
#This package provides the eGroupWare contrib applications.

%package core
Summary: The eGroupWare contrib package
Group: Web/Database
Requires: php >= 4.3 php-mbstring >= 4.3 pear-log => 1.9.3 php-imap php-gd vixie-cron
Provides: egw-core
Conflicts: %{packagename}
%description core
This package provides the eGroupWare contrib applications.

%package %{addressbook}
Summary: The eGroupWare %{addressbook} application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core = %{version}-%{packaging}
Provides: egw-%{addressbook}
%description %{addressbook}
Contact manager with Vcard support.
%{addressbook} is the egroupware default contact application.
It makes use of the egroupware contacts class to store and retrieve 
contact information via SQL, LDAP or Active Directory.

%package %{backup}
Summary: The eGroupWare %{backup} application
Group: Web/Database
AutoReqProv: no
Requires: egw-core = %{version}-%{packaging}
%description %{backup}
An online configurable backup app to store data offline. 
Can store files in zip, tar.gz and tar.bz2 on the local machine 
or Remote via FTP, SMBMOUNT or NFS 

%package %{browser}
Summary: The eGroupWare %{browser} application
Group: Web/Database
AutoReqProv: no
Requires: egw-core = %{version}-%{packaging}
%description %{browser}
Intergrated browser to surf the web within eGroupWare.

%package %{bookmarks}
Summary: The eGroupWare %{bookmarks} application
Group: Web/Database
AutoReqProv: no
Requires: egw-core = %{version}-%{packaging}
%description %{bookmarks}
Manage your bookmarks with eGroupWare. Has Netscape plugin.

%package %{calendar}
Summary: The eGroupWare %{calendar} application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core = %{version}-%{packaging}
%description %{calendar}
Powerful calendar with meeting request system, Alarms, ICal and E-Mail support, 
and ACL security.

%package %{chatty}
Summary: Instant messenger for eGroupWare
Group: Web/Database
AutoReqProv: no
Requires: egw-core = %{version}-%{packaging}
%description %{chatty}
Instant messenger application using AJAX.

%package %{comic}
Summary: The eGroupWare %{comic} application
Group: Web/Database
AutoReqProv: no
Requires: egw-core = %{version}-%{packaging}
%description %{comic}
This application display comic strips.

%package %{developer_tools}
Summary: The eGroupWare %{developer_tools} application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core = %{version}-%{packaging}
%description %{developer_tools}
The TranslationTools allow to create and extend translations-files for eGroupWare. 
They can search the sources for new / added phrases and show you the ones missing in your language. 

%package %{email}
Summary: The eGroupWare %{email} application
Group: Web/Database
AutoReqProv: no
Requires: egw-core = %{version}-%{packaging}, %{packagename}-%{addressbook} = %{version}-%{packaging}
%description %{email}
AngleMail for eGroupWare at www.anglemail.org is an Email reader with multiple accounts and mailbox filtering. Also Anglemail support IMAP, IMAPS, POP3 and POP3S accounts.

%package %{emailadmin}
Summary: The eGroupWare %{emailadmin} application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core = %{version}-%{packaging}
%description %{emailadmin}
EmailAdmin allow to maintain User email accounts 

%package %{felamimail}
Summary: The eGroupWare %{felamimail} application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core = %{version}-%{packaging}, %{packagename}-%{emailadmin} = %{version}-%{packaging}
%description %{felamimail}
The %{felamimail} Email Reader is a other Email application for eGroupWare.

%package %{filemanager}
Summary: The eGroupWare %{filemanager} application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core = %{version}-%{packaging}
%description %{filemanager}
This is the %{filemanager} app for eGroupWare.

%package %{filescenter}
Summary: The eGroupWare %{filescenter} application
Group: Web/Database
AutoReqProv: no
Requires: egw-core = %{version}-%{packaging}
%description %{filescenter}
This is the %{filescenter} app for eGroupWare.

%package %{forum}
Summary: The eGroupWare %{forum} application
Group: Web/Database
AutoReqProv: no
Requires: egw-core = %{version}-%{packaging}
%description %{forum}
This is the %{forum} app for eGroupWare.

%package %{ftp}
Summary: The eGroupWare %{ftp} application
Group: Web/Database
AutoReqProv: no
Requires: egw-core = %{version}-%{packaging} 
%description %{ftp}
This is the %{ftp} app for eGroupWare.

%package %{fudforum}
Summary: The eGroupWare %{fudforum} application
Group: Web/Database
AutoReqProv: no
Requires: egw-core = %{version}-%{packaging}
%description %{fudforum}
This is the %{fudforum} app for eGroupWare.

%package %{headlines}
Summary: The eGroupWare %{headlines} application
Group: Web/Database
AutoReqProv: no
Requires: egw-core = %{version}-%{packaging} 
%description %{headlines}
This is the %{headlines} app for eGroupWare.

%package %{infolog}
Summary: The eGroupWare %{infolog} application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core = %{version}-%{packaging}, egw-%{etemplate} = %{version}-%{packaging}
%description %{infolog}
This is the %{infolog} app for eGroupWare (Notes, ToDo, Phonelogs, CRM).

%package %{jinn}
Summary: The eGroupWare %{jinn} application
Group: Web/Database
AutoReqProv: no
Requires: egw-core = %{version}-%{packaging}
%description %{jinn}
The %{jinn} app is a multi-site, multi-database, multi-user/-group, database driven Content Management System written in and for the eGroupWare Framework.

%package %{manual}
Summary: The eGroupWare %{manual} application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core = %{version}-%{packaging}
%description %{manual}
This is the %{manual} app for eGroupWare: online help system.

%package %{messenger}
Summary: The eGroupWare %{messenger} application
Group: Web/Database
AutoReqProv: no
Requires: egw-core = %{version}-%{packaging} 
%description %{messenger}
This is the %{messenger} app for eGroupWare.

%package %{mydms}
Summary: The eGroupWare %{mydms} application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core = %{version}-%{packaging} 
%description %{mydms}
This is a %{mydms} port to eGroupWare.

%package %{news_admin}
Summary: The eGroupWare %{news_admin} application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core = %{version}-%{packaging} 
%description %{news_admin}
This is the %{news_admin} app for eGroupWare.

%package %{phpbrain}
Summary: The eGroupWare %{phpbrain} application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core = %{version}-%{packaging}, %{packagename}-%{addressbook} = %{version}-%{packaging}
%description %{phpbrain}
This is the %{phpbrain} app for eGroupWare.

%package %{phpldapadmin}
Summary: The eGroupWare %{phpldapadmin} application
Group: Web/Database
AutoReqProv: no
Requires: egw-core = %{version}-%{packaging}
%description %{phpldapadmin}
This is the cire %{phpldapadmin} of eGroupWare.

%package %{phpsysinfo}
Summary: The eGroupWare %{phpsysinfo} application
Group: Web/Database
AutoReqProv: no
Requires: egw-core = %{version}-%{packaging}
%description %{phpsysinfo}
This is the cire %{phpsysinfo} of eGroupWare.

%package %{polls}
Summary: The eGroupWare %{polls} application
Group: Web/Database
AutoReqProv: no
Requires: egw-core = %{version}-%{packaging}
%description %{polls}
This is the %{polls} app for eGroupWare.

%package %{projectmanager}
Summary: The eGroupWare %{projectmanager} application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core = %{version}-%{packaging},
%description %{projectmanager}
The %{projectmanager} is eGroupWare's new project management application.
It's fully integrated into eGroupWare and use the data of InfoLog and Calendar.
Plugable datasources allow to support and manage further applications.

%package %{projects}
Summary: The eGroupWare %{projects} application
Group: Web/Database
AutoReqProv: no
Requires: egw-core = %{version}-%{packaging},
%description %{projects}
This is the %{projects} app for eGroupWare.

%package %{registration}
Summary: The eGroupWare %{registration} application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core = %{version}-%{packaging}
%description %{registration}
This is the %{registration} app for eGroupWare.

%package %{resources}
Summary: The eGroupWare %{resources} application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core = %{version}-%{packaging}
%description %{resources}
%{resources} is a resource booking sysmtem for eGroupWare.
Which integrates into the calendar.

%package %{sambaadmin}
Summary: The eGroupWare %{sambaadmin} application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core = %{version}-%{packaging}
%description %{sambaadmin}
Manage LDAP based sambaacounts and workstations.

%package %{sitemgr}
Summary: The eGroupWare Sitemanager CMS application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core = %{version}-%{packaging}
%description %{sitemgr}
This is the Sitemanager CMS app for eGroupWare.

%package %{stocks}
Summary: The eGroupWare %{stocks} application
Group: Web/Database
AutoReqProv: no
Requires: egw-core = %{version}-%{packaging}
%description %{stocks}
This is the %{stocks} app for eGroupWare.

%package %{syncml}
Summary: The eGroupWare %{syncml} application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core = %{version}-%{packaging}
%description %{syncml}
This is the %{syncml} app for eGroupWare.

%package %{timesheet}
Summary: The eGroupWare timesheet application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core = %{version}-%{packaging}
%description %{timesheet}
Simple timesheet application, which allow to record and report
the times and other expenses. It can be uses as well standalone 
as together with the ProjectManager application.

%package %{tts}
Summary: The eGroupWare trouble ticket system application
Group: Web/Database
AutoReqProv: no
Requires: egw-core = %{version}-%{packaging}
%description %{tts}
This is the trouble ticket system} app for eGroupWare.

%package %{wiki}
Summary: The eGroupWare %{wiki} application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core = %{version}-%{packaging},
%description %{wiki}
This is the %{wiki} app for eGroupWare.

%package %{workflow}
Summary: The eGroupWare %{workflow} application
Group: Web/Database
AutoReqProv: no
Requires: egw-core = %{version}-%{packaging},
%description %{workflow}
This is the %{workflow} app for eGroupWare.

%prep
%setup0 -c -n %{egwdirname}
%setup1 -T -D -a 1 -n %{egwdirname}
%setup2 -T -D -a 2 -n %{egwdirname}
%patch0 -p 0
%patch1 -p 0

%build

%install
[ "%{buildroot}" != "/" ] && rm -rf %{buildroot}
mkdir -p $RPM_BUILD_ROOT%{prefix}/%{egwdirname}
cp -aRf  etc var $RPM_BUILD_ROOT
cp -aRf egroupware/* $RPM_BUILD_ROOT%{prefix}/%{egwdirname}

rm -f $RPM_BUILD_ROOT%{prefix}/%{egwdirname}/.htaccess
rm -rf $RPM_BUILD_ROOT%{prefix}/%{egwdirname}/xmlrpc
rm -rf $RPM_BUILD_ROOT%{prefix}/%{egwdirname}/switchuser
rm -rf $RPM_BUILD_ROOT%{prefix}/%{egwdirname}/skel
rm -rf $RPM_BUILD_ROOT%{prefix}/%{egwdirname}/soap

find $RPM_BUILD_ROOT%{prefix}/%{egwdirname} -name CVS | xargs rm -rf

cd $RPM_BUILD_ROOT%{prefix}/%{egwdirname}
ln -s ../../../var/lib/egroupware/header.inc.php
ln -s sitemgr/sitemgr-link

%clean
[ "%{buildroot}" != "/" ] && rm -rf %{buildroot}

%post
%postun

%files
%dir %{prefix}/%{egwdirname}
%dir /var/lib/egroupware
%{prefix}/%{egwdirname}/about.php
%{prefix}/%{egwdirname}/anon_wrapper.php
%{prefix}/%{egwdirname}/header.inc.php
%{prefix}/%{egwdirname}/header.inc.php.template
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
%{prefix}/%{egwdirname}/admin
%{prefix}/%{egwdirname}/doc
%{prefix}/%{egwdirname}/etemplate
%{prefix}/%{egwdirname}/home
%{prefix}/%{egwdirname}/phpgwapi
%{prefix}/%{egwdirname}/preferences
%{prefix}/%{egwdirname}/setup
%{prefix}/%{egwdirname}/addressbook
%{prefix}/%{egwdirname}/bookmarks
%{prefix}/%{egwdirname}/calendar
%{prefix}/%{egwdirname}/developer_tools
%{prefix}/%{egwdirname}/emailadmin
%{prefix}/%{egwdirname}/felamimail
%{prefix}/%{egwdirname}/filemanager
%{prefix}/%{egwdirname}/infolog
%{prefix}/%{egwdirname}/manual
%{prefix}/%{egwdirname}/mydms
%{prefix}/%{egwdirname}/news_admin
%{prefix}/%{egwdirname}/phpbrain
%{prefix}/%{egwdirname}/polls
%{prefix}/%{egwdirname}/projectmanager
%{prefix}/%{egwdirname}/registration
%{prefix}/%{egwdirname}/resources
%{prefix}/%{egwdirname}/sambaadmin
%{prefix}/%{egwdirname}/sitemgr
%{prefix}/%{egwdirname}/sitemgr-link
%{prefix}/%{egwdirname}/syncml
%{prefix}/%{egwdirname}/timesheet
%{prefix}/%{egwdirname}/wiki
%dir %attr(0755,apache,apache) /var/lib/egroupware/default
%dir %attr(0755,apache,apache) /var/lib/egroupware/default/files
%dir %attr(0755,apache,apache) /var/lib/egroupware/default/backup
%dir %attr(0755,apache,apache) /var/lib/egroupware/sessions
%config %attr(0644,root,root) %{httpdconfd}/egroupware.conf
%config %attr(0640,apache,apache) /var/lib/egroupware/header.inc.php

%files core
%dir %{prefix}/%{egwdirname}
%dir /var/lib/egroupware
%{prefix}/%{egwdirname}/about.php
%{prefix}/%{egwdirname}/anon_wrapper.php
%{prefix}/%{egwdirname}/header.inc.php.template
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
%{prefix}/%{egwdirname}/admin
%{prefix}/%{egwdirname}/doc
%{prefix}/%{egwdirname}/etemplate
%{prefix}/%{egwdirname}/home
%{prefix}/%{egwdirname}/phpgwapi
%{prefix}/%{egwdirname}/preferences
%{prefix}/%{egwdirname}/setup
%dir %attr(0755,apache,apache) /var/lib/egroupware/default
%dir %attr(0755,apache,apache) /var/lib/egroupware/default/files
%dir %attr(0755,apache,apache) /var/lib/egroupware/default/backup
%dir %attr(0755,apache,apache) /var/lib/egroupware/sessions
%config %attr(0644,root,root) %{httpdconfd}/egroupware.conf
%config %attr(0640,apache,apache) /var/lib/egroupware/header.inc.php

#%files contrib
#%{prefix}/%{egwdirname}/browser
#%{prefix}/%{egwdirname}/chatty
#%{prefix}/%{egwdirname}/filescenter
#%{prefix}/%{egwdirname}/messenger
#%{prefix}/%{egwdirname}/projects
#%{prefix}/%{egwdirname}/registration
#%{prefix}/%{egwdirname}/skel
#%{prefix}/%{egwdirname}/soap
#%{prefix}/%{egwdirname}/switchuser
#%{prefix}/%{egwdirname}/tts
#%{prefix}/%{egwdirname}/workflow
#%{prefix}/%{egwdirname}/xmlrpc
#%{prefix}/%{egwdirname}/stocks
#%{prefix}/%{egwdirname}/phpsysinfo
#%{prefix}/%{egwdirname}/phpldapadmin
#%{prefix}/%{egwdirname}/headlines
#%{prefix}/%{egwdirname}/fudforum
#%{prefix}/%{egwdirname}/ftp
#%{prefix}/%{egwdirname}/forum
#%{prefix}/%{egwdirname}/email
#%{prefix}/%{egwdirname}/comic
#%{prefix}/%{egwdirname}/backup

%files %{addressbook}
%{prefix}/%{egwdirname}/%{addressbook}

%files %{backup}
%{prefix}/%{egwdirname}/%{backup}

%files %{bookmarks}
%{prefix}/%{egwdirname}/%{bookmarks}

%files %{browser}
%{prefix}/%{egwdirname}/%{browser}

%files %{calendar}
%{prefix}/%{egwdirname}/%{calendar}

%files %{chatty}
%{prefix}/%{egwdirname}/%{chatty}

%files %{comic}
%{prefix}/%{egwdirname}/%{comic}

%files %{developer_tools}
%{prefix}/%{egwdirname}/%{developer_tools}

%files %{email}
%{prefix}/%{egwdirname}/%{email}

%files %{emailadmin}
%{prefix}/%{egwdirname}/%{emailadmin}

%files %{felamimail}
%{prefix}/%{egwdirname}/%{felamimail}

%files %{filemanager}
%{prefix}/%{egwdirname}/%{filemanager}

%files %{filescenter}
%{prefix}/%{egwdirname}/%{filescenter}

%files %{forum}
%{prefix}/%{egwdirname}/%{forum}

%files %{ftp}
%{prefix}/%{egwdirname}/%{ftp}

%files %{fudforum}
%{prefix}/%{egwdirname}/%{fudforum}

%files %{headlines}
%{prefix}/%{egwdirname}/%{headlines}

%files %{infolog}
%{prefix}/%{egwdirname}/%{infolog}

%files %{jinn}
%{prefix}/%{egwdirname}/%{jinn}

%files %{manual}
%{prefix}/%{egwdirname}/%{manual}

%files %{messenger}
%{prefix}/%{egwdirname}/%{messenger}

%files %{mydms}
%{prefix}/%{egwdirname}/%{mydms}

%files %{news_admin}
%{prefix}/%{egwdirname}/%{news_admin}

%files %{phpbrain}
%{prefix}/%{egwdirname}/%{phpbrain}

%files %{phpldapadmin}
%{prefix}/%{egwdirname}/%{phpldapadmin}

%files %{phpsysinfo}
%{prefix}/%{egwdirname}/%{phpsysinfo}

%files %{polls}
%{prefix}/%{egwdirname}/%{polls}

%files %{projectmanager}
%{prefix}/%{egwdirname}/%{projectmanager}

%files %{projects}
%{prefix}/%{egwdirname}/%{projects}

%files %{registration}
%{prefix}/%{egwdirname}/%{registration}

%files %{resources}
%{prefix}/%{egwdirname}/%{resources}

%files %{sambaadmin}
%{prefix}/%{egwdirname}/%{sambaadmin}

%files %{sitemgr}
%{prefix}/%{egwdirname}/%{sitemgr}

%files %{stocks}
%{prefix}/%{egwdirname}/%{stocks}

%files %{syncml}
%{prefix}/%{egwdirname}/%{syncml}

%files %{timesheet}
%{prefix}/%{egwdirname}/%{timesheet}

%files %{tts}
%{prefix}/%{egwdirname}/%{tts}

%files %{wiki}
%{prefix}/%{egwdirname}/%{wiki}

%files %{workflow}
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

