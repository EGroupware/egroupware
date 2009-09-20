%define packagename eGroupware
%define egwdirname egroupware
%define egwversion 1.6
%define packaging 002
#define epoch 1
%if 0%{?suse_version}
	%define httpdroot /srv/www/htdocs
	%define httpdconfd /etc/apache2/conf.d
	%define source5 egroupware_suse.tar.bz2
	%define distribution SUSE Linux %{?suse_version}
	%define php php5
	%define extra_requires apache2 apache2-mod_php5 php_any_db php5-dom php5-bz2 php5-openssl php5-ctype
	%define cron cron
%endif
%if 0%{?fedora_version}
	%define httpdroot /var/www/html
	%define httpdconfd /etc/httpd/conf.d
	%define osversion %{?fedora_version}
	%define source5 egroupware_fedora.tar.bz2
	%define distribution Fedora Core %{?fedora_version}
	%define php php
	%define extra_requires httpd php-mysql php-xml
	%define cron crontabs
%endif
%if 0%{?mandriva_version}
	%define httpdroot /var/www/html
	%define httpdconfd /etc/httpd/conf.d
	%define osversion %{?mandriva_version}
	%define source5 egroupware_fedora.tar.bz2
	%define distribution Mandriva %{?mandriva_version}
	%define php php
	%define extra_requires apache php-mysql php-dom
	%define cron crontabs
%endif
%if 0%{?rhel_version}
	%define httpdroot /var/www/html
	%define httpdconfd /etc/httpd/conf.d
	%define osversion %{?rhel_version}
	%define source5 egroupware_fedora.tar.bz2
	%define distribution Red Hat %{?rhel_version}
	%define php php
	%define extra_requires httpd php-mysql php-xml
	%define cron crontabs
%endif
%if 0%{?centos_version}
	%define httpdroot /var/www/html
	%define httpdconfd /etc/httpd/conf.d
	%define osversion %{?centos_version}
	%define source5 egroupware_fedora.tar.bz2
	%define distribution CentOS %{?centos_version}
	%define php php
	%define extra_requires httpd php-mysql php-xml
	%define cron crontabs
%endif

%define addressbook addressbook
%define bookmarks bookmarks
%define calendar calendar
%define developer_tools developer_tools
%define egw-pear egw-pear
%define emailadmin emailadmin
%define etemplate etemplate
%define felamimail felamimail
%define filemanager filemanager
%define gallery gallery
%define icalsrv icalsrv
%define infolog infolog
%define importexport importexport
%define manual manual
%define mydms mydms
%define news_admin news_admin
%define notifications notifications
%define phpbrain phpbrain
%define phpsysinfo phpsysinfo
%define polls polls
%define projectmanager projectmanager
%define registration registration
%define resources resources
%define sambaadmin sambaadmin
%define sitemgr sitemgr
%define syncml syncml
%define timesheet timesheet
%define tracker tracker
%define wiki wiki

Name: %{packagename}
Version: %{egwversion}.%{packaging}
Release:
#Epoch: %{epoch}
Summary: eGroupware is a web-based groupware suite written in php.
Distribution: %{distribution}

Group: Web/Database
License: GPL/LGPL
URL: http://www.egroupware.org/
Source0: %{packagename}-%{egwversion}.%{packaging}.tar.bz2
Source1: %{packagename}-egw-pear-%{egwversion}.%{packaging}.tar.bz2
Source2: %{packagename}-icalsrv-%{egwversion}.%{packaging}.tar.bz2
Source3: %{packagename}-mydms-%{egwversion}.%{packaging}.tar.bz2
Source4: %{packagename}-gallery-%{egwversion}.%{packaging}.tar.bz2
Source5: %{?source5}
Source6: %{name}-rpmlintrc
Patch0: class.uiasyncservice.inc.php.patch
BuildRoot: /tmp/%{packagename}-buildroot
Requires: %{php} %{php}-mbstring %{php}-imap %{php}-gd %{php}-pear %{php}-posix %{extra_requires} %{cron} %{packagename}-egw-pear >= %{egwversion}.%{packaging}
Provides: egw-core egw-%{addressbook} egw-%{etemplate}
Conflicts: %{packagename}-core %{packagename}-%{addressbook} %{packagename}-%{bookmarks} %{packagename}-%{calendar} %{packagename}-%{developer_tools} %{packagename}-%{emailadmin} %{packagename}-%{felamimail} %{packagename}-%{filemanager} %{packagename}-%{infolog} %{packagename}-%{importexport} %{packagename}-%{manual} %{packagename}-%{news_admin} %{packagename}-%{notifications} %{packagename}-%{phpbrain} %{packagename}-%{polls} %{packagename}-%{projectmanager} %{packagename}-%{registration} %{packagename}-%{resources} %{packagename}-%{sambaadmin} %{packagename}-%{sitemgr} %{packagename}-%{syncml} %{packagename}-%{timesheet} %{packagename}-%{wiki}
Obsoletes: %{packagename}-%{icalsrv}
#otherwise build fails because of jar files in G2
BuildRequires: unzip

Prefix: /usr/share
Buildarch: noarch
AutoReqProv: no

Vendor: eGroupware
Packager: Ralf Becker <RalfBecker@outdoor-training.de>

%description
eGroupware is a web-based groupware suite written in PHP.

This package provides the eGroupware default applications:

egroupware core with: admin, api, docs, etemplate, prefereces and setup,
addressbook, bookmarks, calendar, translation-tools, emailadmin, felamimail,
filemanager, infolog, manual, news admin, knowledgebase, polls,
projectmanager, resources, sambaadmin, sitemgr, syncml, timesheet, tracker, wiki

It also provides an API for developing additional applications.

Further contributed applications are avalible in single packages.

%package core
Summary: The eGroupware contrib package
Group: Web/Database
Provides: egw-core egw-%{etemplate}
Conflicts: %{packagename}
%description core
This package provides the eGroupware core applications.
%post core
%if 0%{?rhel_version} || 0%{?fedora_version} || 0%{?centos_version}
	chcon -R -u user_u -r object_r -t httpd_sys_content_t /var/lib/egroupware
	setsebool -P httpd_can_network_connect=1
%endif

%package %{addressbook}
Version: %{egwversion}.%{packaging}
Summary: The eGroupware %{addressbook} application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core >= %{egwversion}.%{packaging}
Provides: egw-%{addressbook}
%description %{addressbook}
Contact manager with Vcard support.
%{addressbook} is the egroupware contact application.
It has different backends to store and retrive contacts
from SQL or LDAP.

%package %{bookmarks}
Version: %{egwversion}.%{packaging}
Summary: The eGroupware %{bookmarks} application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{egwversion}.%{packaging}
%description %{bookmarks}
Manage your bookmarks with eGroupware. Has Netscape plugin.

%package %{calendar}
Version: %{egwversion}.%{packaging}
Summary: The eGroupware %{calendar} application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core >= %{egwversion}.%{packaging}
%description %{calendar}
Powerful calendar with meeting request system, Alarms, ICal and E-Mail support,
and ACL security.

%package %{developer_tools}
Version: %{egwversion}.%{packaging}
Summary: The eGroupware %{developer_tools} application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core >= %{egwversion}.%{packaging}
%description %{developer_tools}
The TranslationTools allow to create and extend translations-files for eGroupware.
They can search the sources for new / added phrases and show you the ones missing in your language.

%package egw-pear
Version: %{egwversion}.%{packaging}
Summary: The eGroupware egw-pear application
Group: Web/Database
Requires: %{php}-pear
#Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core >= %{egwversion}.%{packaging}
%description egw-pear
egw-pear contains modified pear classes necessary for eGroupware

%package %{emailadmin}
Version: %{egwversion}.%{packaging}
Summary: The eGroupware %{emailadmin} application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core >= %{egwversion}.%{packaging}, %{packagename}-egw-pear >= %{egwversion}.%{packaging}, php-openssl
%description %{emailadmin}
EmailAdmin allow to maintain User email accounts

%package %{felamimail}
Version: %{egwversion}.%{packaging}
Summary: The eGroupware Webmail application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core >= %{egwversion}.%{packaging}, %{packagename}-%{emailadmin} >= %{egwversion}.%{packaging}, %{packagename}-egw-pear >= %{egwversion}.%{packaging}
%description %{felamimail}
The Email application for eGroupware.

%package %{filemanager}
Version: %{egwversion}.%{packaging}
Summary: The eGroupware %{filemanager} application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core >= %{egwversion}.%{packaging}, egw-pear >= %{egwversion}.%{packaging}
%description %{filemanager}
This is the %{filemanager} app for eGroupware.

%package %{gallery}
Version: %{egwversion}.%{packaging}
Summary: The eGroupware %{gallery} application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core >= %{egwversion}.%{packaging}, egw-pear >= %{egwversion}.%{packaging}
%description %{gallery}
An embedded Gallery2 for eGroupware.

%package %{icalsrv}
Version: %{egwversion}.%{packaging}
Summary: The eGroupware %{icalsrv} application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{egwversion}.%{packaging}
%description %{icalsrv}
This is the old %{icalsrv} app for eGroupware.
It is NOT necessary for GroupDAV, CalDAV or CardDAV,
which is build into the eGroupware core.

%package %{infolog}
Version: %{egwversion}.%{packaging}
Summary: The eGroupware %{infolog} application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core >= %{egwversion}.%{packaging}
%description %{infolog}
This is the %{infolog} app for eGroupware (Notes, ToDo, Phonelogs, CRM).

%package %{importexport}
Version: %{egwversion}.%{packaging}
Summary: The eGroupware %{importexport} application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core >= %{egwversion}.%{packaging}
%description %{importexport}
This is the %{importexport} app for eGroupware. It includes a comandline client.

#%package %{jinn}
#Version: %{egwversion}.%{packaging}
#Summary: The eGroupware %{jinn} application
#Group: Web/Database
#AutoReqProv: no
#Requires: egw-core >= %{egwversion}.%{packaging}
#%description %{jinn}
#The %{jinn} app is a multi-site, multi-database, multi-user/-group, database driven Content Management System written in and for the eGroupware Framework.

%package %{manual}
Version: %{egwversion}.%{packaging}
Summary: The eGroupware %{manual} application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core >= %{egwversion}.%{packaging}
%description %{manual}
This is the %{manual} app for eGroupware: online help system.

%package %{mydms}
Version: %{egwversion}.%{packaging}
Summary: The eGroupware %{mydms} application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core >= %{egwversion}.%{packaging}, egw-pear >= %{egwversion}.%{packaging}
%description %{mydms}
This is a %{mydms} port to eGroupware.

%package %{news_admin}
Version: %{egwversion}.%{packaging}
Summary: The eGroupware %{news_admin} application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core >= %{egwversion}.%{packaging}
%description %{news_admin}
This is the %{news_admin} app for eGroupware.

%package %{notifications}
Version: %{egwversion}.%{packaging}
Summary: The eGroupware %{notifications} application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core >= %{egwversion}.%{packaging}
%description %{notifications}
This is the %{notifications} app for eGroupware.

%package %{phpbrain}
Version: %{egwversion}.%{packaging}
Summary: The eGroupware %{phpbrain} application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core >= %{egwversion}.%{packaging}, %{packagename}-%{addressbook} >= %{egwversion}.%{packaging}
%description %{phpbrain}
This is the %{phpbrain} app for eGroupware.

%package %{phpsysinfo}
Version: %{egwversion}.%{packaging}
Summary: The eGroupware %{phpsysinfo} application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core >= %{egwversion}.%{packaging}
%description %{phpsysinfo}
This is the %{phpsysinfo} app for eGroupware.

%package %{polls}
Version: %{egwversion}.%{packaging}
Summary: The eGroupware %{polls} application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{egwversion}.%{packaging}
%description %{polls}
This is the %{polls} app for eGroupware.

%package %{projectmanager}
Version: %{egwversion}.%{packaging}
Summary: The eGroupware %{projectmanager} application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core >= %{egwversion}.%{packaging},
%description %{projectmanager}
The %{projectmanager} is eGroupware's new project management application.
It's fully integrated into eGroupware and use the data of InfoLog and Calendar.
Plugable datasources allow to support and manage further applications.

%package %{registration}
Version: %{egwversion}.%{packaging}
Summary: The eGroupware %{registration} application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core >= %{egwversion}.%{packaging}
%description %{registration}
This is the %{registration} app for eGroupware.

%package %{resources}
Version: %{egwversion}.%{packaging}
Summary: The eGroupware %{resources} application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core >= %{egwversion}.%{packaging}
%description %{resources}
%{resources} is a resource booking sysmtem for eGroupware.
Which integrates into the calendar.

%package %{sambaadmin}
Version: %{egwversion}.%{packaging}
Summary: The eGroupware %{sambaadmin} application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core >= %{egwversion}.%{packaging}
%description %{sambaadmin}
Manage LDAP based sambaacounts and workstations.

%package %{sitemgr}
Version: %{egwversion}.%{packaging}
Summary: The eGroupware Sitemanager CMS application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core >= %{egwversion}.%{packaging}
%description %{sitemgr}
This is the Sitemanager CMS app for eGroupware.

%package %{syncml}
Version: %{egwversion}.%{packaging}
Summary: The eGroupware %{syncml} application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core >= %{egwversion}.%{packaging}
%description %{syncml}
This is the %{syncml} app for eGroupware.

%package %{timesheet}
Version: %{egwversion}.%{packaging}
Summary: The eGroupware timesheet application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core >= %{egwversion}.%{packaging}
%description %{timesheet}
Simple timesheet application, which allow to record and report
the times and other expenses. It can be uses as well standalone
as together with the ProjectManager application.

%package %{tracker}
Version: %{egwversion}.%{packaging}
Summary: The eGroupware trouble ticket system application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{egwversion}.%{packaging}
%description %{tracker}
This is the trouble ticket system app for eGroupware.

%package %{wiki}
Version: %{egwversion}.%{packaging}
Summary: The eGroupware %{wiki} application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core >= %{egwversion}.%{packaging},
%description %{wiki}
This is the %{wiki} app for eGroupware.

#%package %{workflow}
#Version: %{egwversion}.%{packaging}
#Summary: The eGroupware %{workflow} application
#Group: Web/Database
#AutoReqProv: no
#Requires: egw-core >= %{egwversion}.%{packaging},
#%description %{workflow}
#This is the %{workflow} app for eGroupware.

%prep
%setup0 -c -n %{egwdirname}
%setup1 -T -D -a 1 -n %{egwdirname}
%setup2 -T -D -a 2 -n %{egwdirname}
%setup3 -T -D -a 3 -n %{egwdirname}
%setup4 -T -D -a 4 -n %{egwdirname}
%setup5 -T -D -a 5 -n %{egwdirname}
%patch0 -p 0

%build

%install
[ "%{buildroot}" != "/" ] && rm -rf %{buildroot}
mkdir -p $RPM_BUILD_ROOT%{prefix}/%{egwdirname}
cp -aRf  etc var $RPM_BUILD_ROOT
cp -aRf egroupware/* $RPM_BUILD_ROOT%{prefix}/%{egwdirname}

rm -f $RPM_BUILD_ROOT%{prefix}/%{egwdirname}/.htaccess
rm -rf $RPM_BUILD_ROOT%{prefix}/%{egwdirname}/switchuser
rm -rf $RPM_BUILD_ROOT%{prefix}/%{egwdirname}/skel
rm -rf $RPM_BUILD_ROOT%{prefix}/%{egwdirname}/soap
rm -rf $RPM_BUILD_ROOT%{prefix}/%{egwdirname}/xmlrpc
rm -rf $RPM_BUILD_ROOT%{prefix}/%{egwdirname}/messenger
rm -rf $RPM_BUILD_ROOT%{prefix}/%{egwdirname}/workflow
rm -rf $RPM_BUILD_ROOT%{prefix}/%{egwdirname}/jinn
rm -f $RPM_BUILD_ROOT%{prefix}/%{egwdirname}/admin/inc/*.orig

find $RPM_BUILD_ROOT%{prefix}/%{egwdirname} -name .svn | xargs rm -rf

cd $RPM_BUILD_ROOT%{prefix}/%{egwdirname}
ln -s ../../../var/lib/egroupware/header.inc.php

%clean
[ "%{buildroot}" != "/" ] && rm -rf %{buildroot}

%post
%if 0%{?rhel_version} || 0%{?fedora_version} || 0%{?centos_version}
	chcon -R -u user_u -r object_r -t httpd_sys_content_t /var/lib/egroupware
	setsebool -P httpd_can_network_connect=1
%endif
%postun

%files
%defattr(-,root,root)
%dir %attr(0755,root,root) %{prefix}/%{egwdirname}
%dir %attr(0755,root,root) /var/lib/egroupware
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
%{prefix}/%{egwdirname}/svn-helper.php
%{prefix}/%{egwdirname}/webdav.php
%{prefix}/%{egwdirname}/groupdav.php
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
%{prefix}/%{egwdirname}/icalsrv
%{prefix}/%{egwdirname}/infolog
%{prefix}/%{egwdirname}/importexport
%{prefix}/%{egwdirname}/manual
%{prefix}/%{egwdirname}/mydms
%{prefix}/%{egwdirname}/news_admin
%{prefix}/%{egwdirname}/notifications
%{prefix}/%{egwdirname}/phpbrain
%{prefix}/%{egwdirname}/phpsysinfo
%{prefix}/%{egwdirname}/polls
%{prefix}/%{egwdirname}/projectmanager
%{prefix}/%{egwdirname}/registration
%{prefix}/%{egwdirname}/resources
%{prefix}/%{egwdirname}/sambaadmin
%{prefix}/%{egwdirname}/sitemgr
%{prefix}/%{egwdirname}/syncml
%{prefix}/%{egwdirname}/timesheet
%{prefix}/%{egwdirname}/tracker
%{prefix}/%{egwdirname}/wiki
%attr(0644,root,root) /etc/cron.d/egroupware
%config %attr(0644,root,root) %{httpdconfd}/egroupware.conf
%if 0%{?suse_version}
	%dir %attr(0755,root,root) /etc/apache2
	%dir %attr(0755,root,root) %{httpdconfd}
	%dir %attr(0755,wwwrun,www) /var/lib/egroupware/default
	%dir %attr(0755,wwwrun,www) /var/lib/egroupware/default/files
	%dir %attr(0755,wwwrun,www) /var/lib/egroupware/default/backup
	%config %attr(0640,wwwrun,www) /var/lib/egroupware/header.inc.php
%endif
%if 0%{?rhel_version} || 0%{?fedora_version} || 0%{?centos_version}
	%dir %attr(0755,apache,apache) /var/lib/egroupware/default
	%dir %attr(0755,apache,apache) /var/lib/egroupware/default/files
	%dir %attr(0755,apache,apache) /var/lib/egroupware/default/backup
	%config %attr(0640,apache,apache) /var/lib/egroupware/header.inc.php
%endif
%if 0%{?mandriva_version}
	%dir %attr(0755,apache,apache) /var/lib/egroupware/default
	%dir %attr(0755,apache,apache) /var/lib/egroupware/default/files
	%dir %attr(0755,apache,apache) /var/lib/egroupware/default/backup
	%config %attr(0640,apache,apache) /var/lib/egroupware/header.inc.php
%endif

%files core
%defattr(-,root,root)
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
%{prefix}/%{egwdirname}/groupdav.php
%{prefix}/%{egwdirname}/webdav.php
%{prefix}/%{egwdirname}/admin
%{prefix}/%{egwdirname}/doc
%{prefix}/%{egwdirname}/etemplate
%{prefix}/%{egwdirname}/home
%{prefix}/%{egwdirname}/phpgwapi
%{prefix}/%{egwdirname}/preferences
%{prefix}/%{egwdirname}/setup
%attr(0644,root,root) /etc/cron.d/egroupware
%config %attr(0644,root,root) %{httpdconfd}/egroupware.conf
%if 0%{?suse_version}
	%dir %attr(0755,root,root) /etc/apache2
	%dir %attr(0755,root,root) %{httpdconfd}
	%dir %attr(0755,wwwrun,www) /var/lib/egroupware/default
	%dir %attr(0755,wwwrun,www) /var/lib/egroupware/default/files
	%dir %attr(0755,wwwrun,www) /var/lib/egroupware/default/backup
	%config %attr(0640,wwwrun,www) /var/lib/egroupware/header.inc.php
%endif
%if 0%{?rhel_version} || 0%{?fedora_version} || 0%{?centos_version}
	%dir %attr(0755,apache,apache) /var/lib/egroupware/default
	%dir %attr(0755,apache,apache) /var/lib/egroupware/default/files
	%dir %attr(0755,apache,apache) /var/lib/egroupware/default/backup
	%config %attr(0640,apache,apache) /var/lib/egroupware/header.inc.php
%endif
%if 0%{?mandriva_version}
	%dir %attr(0755,apache,apache) /var/lib/egroupware/default
	%dir %attr(0755,apache,apache) /var/lib/egroupware/default/files
	%dir %attr(0755,apache,apache) /var/lib/egroupware/default/backup
	%config %attr(0640,apache,apache) /var/lib/egroupware/header.inc.php
%endif

%files %{addressbook}
%defattr(-,root,root)
%{prefix}/%{egwdirname}/%{addressbook}

%files %{calendar}
%defattr(-,root,root)
%{prefix}/%{egwdirname}/%{calendar}

%files %{developer_tools}
%defattr(-,root,root)
%{prefix}/%{egwdirname}/%{developer_tools}

%files egw-pear
%defattr(-,root,root)
%{prefix}/%{egwdirname}/egw-pear

%files %{emailadmin}
%defattr(-,root,root)
%{prefix}/%{egwdirname}/%{emailadmin}

%files %{felamimail}
%defattr(-,root,root)
%{prefix}/%{egwdirname}/%{felamimail}

%files %{filemanager}
%defattr(-,root,root)
%{prefix}/%{egwdirname}/%{filemanager}

%files %{gallery}
%defattr(-,root,root)
%{prefix}/%{egwdirname}/%{gallery}

%files %{icalsrv}
%defattr(-,root,root)
%{prefix}/%{egwdirname}/%{icalsrv}

%files %{infolog}
%defattr(-,root,root)
%{prefix}/%{egwdirname}/%{infolog}

%files %{importexport}
%defattr(-,root,root)
%{prefix}/%{egwdirname}/%{importexport}

#%files %{jinn}
#%defattr(-,root,root)
#%{prefix}/%{egwdirname}/%{jinn}

%files %{manual}
%defattr(-,root,root)
%{prefix}/%{egwdirname}/%{manual}

%files %{mydms}
%defattr(-,root,root)
%{prefix}/%{egwdirname}/%{mydms}

%files %{news_admin}
%defattr(-,root,root)
%{prefix}/%{egwdirname}/%{news_admin}

%files %{notifications}
%defattr(-,root,root)
%{prefix}/%{egwdirname}/%{notifications}

%files %{phpbrain}
%defattr(-,root,root)
%{prefix}/%{egwdirname}/%{phpbrain}

%files %{phpsysinfo}
%defattr(-,root,root)
%{prefix}/%{egwdirname}/%{phpsysinfo}

%files %{polls}
%defattr(-,root,root)
%{prefix}/%{egwdirname}/%{polls}

%files %{projectmanager}
%defattr(-,root,root)
%{prefix}/%{egwdirname}/%{projectmanager}

%files %{registration}
%defattr(-,root,root)
%{prefix}/%{egwdirname}/%{registration}

%files %{resources}
%defattr(-,root,root)
%{prefix}/%{egwdirname}/%{resources}

%files %{sambaadmin}
%defattr(-,root,root)
%{prefix}/%{egwdirname}/%{sambaadmin}

%files %{sitemgr}
%defattr(-,root,root)
%{prefix}/%{egwdirname}/%{sitemgr}

%files %{timesheet}
%defattr(-,root,root)
%{prefix}/%{egwdirname}/%{timesheet}

%files %{tracker}
%defattr(-,root,root)
%{prefix}/%{egwdirname}/%{tracker}

%files %{wiki}
%defattr(-,root,root)
%{prefix}/%{egwdirname}/%{wiki}

#%files %{workflow}
#%defattr(-,root,root)
#%{prefix}/%{egwdirname}/%{workflow}


%changelog
* Mon Jul 20 2009 Ralf Becker <RalfBecker@outdoor-training.de> 1.6.002
- eGroupware 1.6.002 security and bugfix release
- fixes 3 security problems:
  + FCKeditor (remote file upload)
  + tracker (XSS problem)
  + knowledgebase (SQL injection)
- added HTML Purifier as preventive measure for FCKeditor content
- tons of bugfixes since initial 1.6.001 release

* Mon Nov 24 2008 Ralf Becker <RalfBecker@outdoor-training.de> 1.6.001
- eGroupware 1.6.001 final 1.6 release

* Sun Nov 16 2008 Ralf Becker <RalfBecker@outdoor-training.de> 1.6.rc5
- eGroupware 1.6.rc5 5. release candidate for 1.6 release

* Sun Nov 9 2008 Ralf Becker <RalfBecker@outdoor-training.de> 1.6.rc4
- eGroupware 1.6.rc4 4. release candidate for 1.6 release

* Wed Oct 29 2008 Ralf Becker <RalfBecker@outdoor-training.de> 1.6.rc3
- eGroupware 1.6.rc3 3. release candidate for 1.6 release

* Wed Oct 22 2008 Ralf Becker <RalfBecker@outdoor-training.de> 1.6.rc2
- eGroupware 1.6.rc2 2. release candidate for 1.6 release

* Fri Oct 10 2008 Ralf Becker <RalfBecker@outdoor-training.de> 1.6.rc1
- eGroupware 1.6.rc1 first release candidate for 1.6 release

* Tue Jul 22 2008 Ralf Becker <RalfBecker@outdoor-training.de> 1.6.pre1
- eGroupware 1.6.pre1 first preview of upcomming 1.6 release

* Mon Apr 15 2008 Ralf Becker <RalfBecker@outdoor-training.de> 1.4.004
- eGroupware 1.4.004 FCKeditor update (2.6) & security release

* Mon Mar 19 2008 Ralf Becker <RalfBecker@outdoor-training.de> 1.4.003
- eGroupware 1.4.003 security & maintainace release

* Mon Sep 24 2007 Ralf Becker <RalfBecker@outdoor-training.de> 1.4.002
- eGroupware 1.4.002 bugfix & maintainace release

* Mon Jun 4 2007 Ralf Becker <RalfBecker@outdoor-training.de> 1.4.001
- final eGroupware 1.4 release
