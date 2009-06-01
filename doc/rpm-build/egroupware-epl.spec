%define packagename egroupware-epl
%define egw_packagename eGroupware
%define egwdirname egroupware
%define egwversion 1.7.002
%define eplversion 9.1
%define packaging 20090601
#define epoch 1

Name: %{packagename}
Version: %{eplversion}.%{packaging}
Release:
#Epoch: %{epoch}
Summary: EGroupware is a web-based groupware suite written in php.
Distribution: %{distribution}
Group: Web/Database
License: GPLv2 with exception of stylite module, which is proprietary
URL: http://www.stylite.de/EPL

Prefix: /usr/share
%define egwdatadir /var/lib/egroupware

%if 0%{?suse_version}
	%define httpdconfd /etc/apache2/conf.d
	%define source5 egroupware_suse.tar.bz2
	%define distribution SUSE Linux %{?suse_version}
	%define php php5
	%define extra_requires apache2 apache2-mod_php5 php_any_db php5-dom
	%define cron cron
	%define rpm_post_install /usr/bin/php5 %{prefix}/%{egwdirname}/doc/rpm-build/rpm_post_install.php --php /usr/bin/php5 --start_db /etc/init.d/mysql --autostart_db /sbin/chkconfig --level 3 mysql on --start_webserver /etc/init.d/apache2 --autostart_webserver /sbin/chkconfig --level 3 apache2 on
%else
	%define php php
	%define httpdconfd /etc/httpd/conf.d
	%define source5 egroupware_fedora.tar.bz2
	%define cron crontabs
	%define rpm_post_install /usr/bin/php %{prefix}/%{egwdirname}/doc/rpm-build/rpm_post_install.php
%endif
%if 0%{?fedora_version}
	%define osversion %{?fedora_version}
	%define distribution Fedora Core %{?fedora_version}
	%define extra_requires httpd php-mysql php-xml
%endif
%if 0%{?mandriva_version}
	%define osversion %{?mandriva_version}
	%define distribution Mandriva %{?mandriva_version}
	%define extra_requires apache php-mysql php-dom
%endif
%if 0%{?rhel_version}
	%define osversion %{?rhel_version}
	%define distribution Red Hat %{?rhel_version}
	%define extra_requires httpd php-mysql php-xml
%endif
%if 0%{?centos_version}
	%define osversion %{?centos_version}
	%define distribution CentOS %{?centos_version}
	%define extra_requires httpd php-mysql php-xml
%endif

Source0: %{packagename}-%{eplversion}.%{packaging}.tar.bz2
Source1: %{packagename}-egw-pear-%{eplversion}.%{packaging}.tar.bz2
Source2: %{packagename}-stylite-%{eplversion}.%{packaging}.tar.bz2
Source3: %{packagename}-gallery-%{eplversion}.%{packaging}.tar.bz2
Source4: %{?source5}
Source5: %{name}-%{eplversion}-rpmlintrc
Patch0: class.uiasyncservice.inc.php.patch
BuildRoot: %{_tmppath}/%{packagename}-buildroot

Requires: %{php} >= 5.1.2
Requires: %{php}-mbstring %{php}-imap %{php}-gd %{php}-pear %{extra_requires} %{cron}
Requires: %{packagename}-core            = %{eplversion}.%{packaging}
Requires: %{packagename}-egw-pear        = %{eplversion}.%{packaging}
Requires: %{packagename}-stylite         = %{eplversion}.%{packaging}
#Requires: %{packagename}-addressbook    = %{eplversion}.%{packaging}
Requires: %{packagename}-bookmarks       = %{eplversion}.%{packaging}
Requires: %{packagename}-calendar        = %{eplversion}.%{packaging}
Requires: %{packagename}-developer_tools = %{eplversion}.%{packaging}
Requires: %{packagename}-emailadmin      = %{eplversion}.%{packaging}
Requires: %{packagename}-felamimail      = %{eplversion}.%{packaging}
Requires: %{packagename}-filemanager     = %{eplversion}.%{packaging}
Requires: %{packagename}-infolog         = %{eplversion}.%{packaging}
Requires: %{packagename}-importexport    = %{eplversion}.%{packaging}
Requires: %{packagename}-manual          = %{eplversion}.%{packaging}
Requires: %{packagename}-news_admin      = %{eplversion}.%{packaging}
Requires: %{packagename}-notifications   = %{eplversion}.%{packaging}
Requires: %{packagename}-phpbrain        = %{eplversion}.%{packaging}
Requires: %{packagename}-polls           = %{eplversion}.%{packaging}
Requires: %{packagename}-projectmanager  = %{eplversion}.%{packaging}
Requires: %{packagename}-registration    = %{eplversion}.%{packaging}
Requires: %{packagename}-resources       = %{eplversion}.%{packaging}
Requires: %{packagename}-sambaadmin      = %{eplversion}.%{packaging}
Requires: %{packagename}-sitemgr         = %{eplversion}.%{packaging}
Requires: %{packagename}-syncml          = %{eplversion}.%{packaging}
Requires: %{packagename}-timesheet       = %{eplversion}.%{packaging}
Requires: %{packagename}-wiki            = %{eplversion}.%{packaging}
Provides: %{egw_packagename}
Obsoletes: %{egw_packagename} %{egw_packagename}-core %{egw_packagename}-addressbook %{egw_packagename}-bookmarks %{egw_packagename}-calendar %{egw_packagename}-developer_tools %{egw_packagename}-emailadmin %{egw_packagename}-felamimail %{egw_packagename}-filemanager %{egw_packagename}-infolog %{egw_packagename}-importexport %{egw_packagename}-manual %{egw_packagename}-news_admin %{egw_packagename}-notifications %{egw_packagename}-phpbrain %{egw_packagename}-polls %{egw_packagename}-projectmanager %{egw_packagename}-registration %{egw_packagename}-resources %{egw_packagename}-sambaadmin %{egw_packagename}-sitemgr %{egw_packagename}-syncml %{egw_packagename}-timesheet %{egw_packagename}-wiki
%post
%if 0%{?rhel_version} || 0%{?fedora_version} || 0%{?centos_version}
	chcon -R -u user_u -r object_r -t httpd_sys_content_t %{egwdatadir}
	setsebool -P httpd_can_network_connect=1
%endif
%{rpm_post_install} -v
%postun

#otherwise build fails because of jar files in G2
BuildRequires: unzip

Buildarch: noarch
AutoReqProv: no

Vendor: Stylite GmbH [http://www.stylite.de/]
Packager: Ralf Becker <rb@stylite.de>

%description
EGroupware is a web-based groupware suite written in PHP.

EGroupware EPL combines Stylite's actual EGroupware enhancements and the recent development of the EGroupware open source project in one software package.
- Brand new Stylite features, which are not available publicly in the community edition of EGroupware
- The latest possible state of open source community features.

This package automatically requires the EGroupware default applications:

egroupware core with: admin, api, docs, etemplate, prefereces and setup,
addressbook, bookmarks, calendar, translation-tools, emailadmin, felamimail,
filemanager, infolog, manual, news admin, knowledgebase, polls,
projectmanager, resources, sambaadmin, sitemgr, syncml, timesheet, tracker, wiki

It also provides an API for developing additional applications.

Further contributed applications are available as separate packages.

%package core
Summary: The EGroupware core
Group: Web/Database
Provides: egw-core %{eplversion}.%{packaging}
%description core
This package provides the EGroupware core applications.

%package egw-pear
Version: %{eplversion}.%{packaging}
Summary: The EGroupware egw-pear application
Group: Web/Database
Requires: %{php}-pear
AutoReqProv: no
Requires: egw-core = %{eplversion}.%{packaging}
%description egw-pear
egw-pear contains modified pear classes necessary for EGroupware

# addressbook is part of core now, as it contains required classes for accounts
#%package addressbook
#Version: %{eplversion}.%{packaging}
#Summary: The EGroupware addressbook application
#Group: Web/Database
#AutoReqProv: no
#Requires: egw-core = %{eplversion}.%{packaging}
#%description addressbook
#Contact manager with Vcard support.
#Addressbook is the egroupware contact application.
#It has different backends to store and retrive contacts
#from SQL or LDAP.

%package bookmarks
Version: %{eplversion}.%{packaging}
Summary: The EGroupware bookmarks application
Group: Web/Database
AutoReqProv: no
Requires: egw-core = %{eplversion}.%{packaging}
%description bookmarks
Manage your bookmarks with EGroupware. Has Netscape plugin.

%package calendar
Version: %{eplversion}.%{packaging}
Summary: The EGroupware calendar application
Group: Web/Database
AutoReqProv: no
Requires: egw-core = %{eplversion}.%{packaging}
%description calendar
Powerful calendar with meeting request system, Alarms, ICal and E-Mail support,
and ACL security.

%package developer_tools
Version: %{eplversion}.%{packaging}
Summary: The EGroupware developer_tools application
Group: Web/Database
AutoReqProv: no
Requires: egw-core = %{eplversion}.%{packaging}
%description developer_tools
The TranslationTools allow to create and extend translations-files for EGroupware.
They can search the sources for new / added phrases and show you the ones missing in your language.

%package emailadmin
Version: %{eplversion}.%{packaging}
Summary: The EGroupware emailadmin application
Group: Web/Database
AutoReqProv: no
Requires: egw-core = %{eplversion}.%{packaging}
Requires: %{packagename}-egw-pear = %{eplversion}.%{packaging}
%description emailadmin
EmailAdmin allow to maintain User email accounts

%package felamimail
Version: %{eplversion}.%{packaging}
Summary: The EGroupware Webmail application
Group: Web/Database
AutoReqProv: no
Requires: egw-core = %{eplversion}.%{packaging}
Requires: %{packagename}-emailadmin = %{eplversion}.%{packaging}
Requires: %{packagename}-egw-pear = %{eplversion}.%{packaging}
%description felamimail
The Email application for EGroupware.

%package filemanager
Version: %{eplversion}.%{packaging}
Summary: The EGroupware filemanager application
Group: Web/Database
AutoReqProv: no
Requires: egw-core = %{eplversion}.%{packaging}
Requires: %{packagename}-egw-pear = %{eplversion}.%{packaging}
%description filemanager
This is the filemanager app for EGroupware.

%package gallery
Version: %{eplversion}.%{packaging}
Summary: The EGroupware gallery application
Group: Web/Database
AutoReqProv: no
Requires: egw-core = %{eplversion}.%{packaging}
%description gallery
An embedded Gallery2 for EGroupware.

%package infolog
Version: %{eplversion}.%{packaging}
Summary: The EGroupware infolog application
Group: Web/Database
AutoReqProv: no
Requires: egw-core = %{eplversion}.%{packaging}
%description infolog
This is the infolog app for EGroupware (Notes, ToDo, Phonelogs, CRM).

%package importexport
Version: %{eplversion}.%{packaging}
Summary: The EGroupware importexport application
Group: Web/Database
AutoReqProv: no
Requires: egw-core = %{eplversion}.%{packaging}
%description importexport
This is the importexport app for EGroupware. It includes a comandline client.

%package manual
Version: %{eplversion}.%{packaging}
Summary: The EGroupware manual application
Group: Web/Database
AutoReqProv: no
Requires: egw-core = %{eplversion}.%{packaging}
Requires: %{packagename}-wiki = %{eplversion}.%{packaging}
%description manual
This is the manual app for EGroupware: online help system.

#%package mydms
#Version: %{eplversion}.%{packaging}
#Summary: The EGroupware mydms application
#Group: Web/Database
#AutoReqProv: no
#Requires: egw-core = %{eplversion}.%{packaging}
#Requires: %{packagename}-egw-pear = %{eplversion}.%{packaging}
#%description mydms
#This is a mydms port to EGroupware.

%package news_admin
Version: %{eplversion}.%{packaging}
Summary: The EGroupware news_admin application
Group: Web/Database
AutoReqProv: no
Requires: egw-core = %{eplversion}.%{packaging}
%description news_admin
This is the news_admin app for EGroupware.

%package notifications
Version: %{eplversion}.%{packaging}
Summary: The EGroupware notifications application
Group: Web/Database
AutoReqProv: no
Requires: egw-core = %{eplversion}.%{packaging}
%description notifications
This is the notifications app for EGroupware.

%package phpbrain
Version: %{eplversion}.%{packaging}
Summary: The EGroupware phpbrain application
Group: Web/Database
AutoReqProv: no
Requires: egw-core = %{eplversion}.%{packaging}
%description phpbrain
This is a knowledgebase for EGroupware.

%package phpsysinfo
Version: %{eplversion}.%{packaging}
Summary: The EGroupware phpsysinfo application
Group: Web/Database
AutoReqProv: no
Requires: egw-core = %{eplversion}.%{packaging}
%description phpsysinfo
This is the phpsysinfo app for EGroupware.

%package polls
Version: %{eplversion}.%{packaging}
Summary: The EGroupware polls application
Group: Web/Database
AutoReqProv: no
Requires: egw-core = %{eplversion}.%{packaging}
%description polls
This is the polls app for EGroupware.

%package projectmanager
Version: %{eplversion}.%{packaging}
Summary: The EGroupware projectmanager application
Group: Web/Database
AutoReqProv: no
Requires: egw-core = %{eplversion}.%{packaging},
%description projectmanager
The projectmanager is EGroupware's new project management application.
It's fully integrated into EGroupware and use the data of InfoLog and Calendar.
Plugable datasources allow to support and manage further applications.

%package registration
Version: %{eplversion}.%{packaging}
Summary: The EGroupware registration application
Group: Web/Database
AutoReqProv: no
Requires: egw-core = %{eplversion}.%{packaging}
%description registration
This is the registration app for EGroupware.

%package resources
Version: %{eplversion}.%{packaging}
Summary: The EGroupware resources application
Group: Web/Database
AutoReqProv: no
Requires: egw-core = %{eplversion}.%{packaging}
%description resources
resources is a resource booking sysmtem for EGroupware.
Which integrates into the calendar.

%package sambaadmin
Version: %{eplversion}.%{packaging}
Summary: The EGroupware sambaadmin application
Group: Web/Database
AutoReqProv: no
Requires: egw-core = %{eplversion}.%{packaging}
%description sambaadmin
Manage LDAP based sambaacounts and workstations.

%package sitemgr
Version: %{eplversion}.%{packaging}
Summary: The EGroupware Sitemanager CMS application
Group: Web/Database
AutoReqProv: no
Requires: egw-core = %{eplversion}.%{packaging}
%description sitemgr
This is the Sitemanager CMS app for EGroupware.

%package stylite
Version: %{eplversion}.%{packaging}
Summary: Stylite EPL enhancements
License: proprietary, see http://www.stylite.de/EPL
Group: Web/Database
AutoReqProv: no
Requires: egw-core = %{eplversion}.%{packaging}
%description stylite
The package contains Stylite proprietary EPL enhancements:
- stylite.links stream wrapper allows browsing of app directories
- filemanger favorites

%package syncml
Version: %{eplversion}.%{packaging}
Summary: The EGroupware syncml application
Group: Web/Database
AutoReqProv: no
Requires: egw-core = %{eplversion}.%{packaging}
Requires: %{packagename}-egw-pear = %{eplversion}.%{packaging}
%description syncml
This is the syncml app for EGroupware.

%package timesheet
Version: %{eplversion}.%{packaging}
Summary: The EGroupware timesheet application
Group: Web/Database
AutoReqProv: no
Requires: egw-core = %{eplversion}.%{packaging}
%description timesheet
Simple timesheet application, which allow to record and report
the times and other expenses. It can be uses as well standalone
as together with the ProjectManager application.

%package tracker
Version: %{eplversion}.%{packaging}
Summary: The EGroupware trouble ticket system application
Group: Web/Database
AutoReqProv: no
Requires: egw-core = %{eplversion}.%{packaging}
%description tracker
This is the trouble ticket system app for EGroupware.

%package wiki
Version: %{eplversion}.%{packaging}
Summary: The EGroupware wiki application
Group: Web/Database
AutoReqProv: no
Requires: egw-core = %{eplversion}.%{packaging},
%description wiki
This is the wiki app for EGroupware.

#%package workflow
#Version: %{eplversion}.%{packaging}
#Summary: The EGroupware workflow application
#Group: Web/Database
#AutoReqProv: no
#Requires: egw-core = %{eplversion}.%{packaging},
#%description workflow
#This is the workflow app for EGroupware.

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
cp -aRf etc var $RPM_BUILD_ROOT
cp -aRf egroupware/* $RPM_BUILD_ROOT%{prefix}/%{egwdirname}

cd %{buildroot}%{prefix}/%{egwdirname}
ln -s ../../..%{egwdatadir}/header.inc.php

%clean
[ "%{buildroot}" != "/" ] && rm -rf %{buildroot}

# egroupware metapackage seems to need some files to be build ...
%files
%defattr(-,root,root)
%dir %{prefix}/%{egwdirname}
%dir %{egwdatadir}

%files core
%defattr(-,root,root)
%dir %{prefix}/%{egwdirname}
%dir %{egwdatadir}
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
%{prefix}/%{egwdirname}/svn-helper.php
%{prefix}/%{egwdirname}/xajax.php
%{prefix}/%{egwdirname}/xmlrpc.php
%{prefix}/%{egwdirname}/groupdav.php
%{prefix}/%{egwdirname}/webdav.php
%{prefix}/%{egwdirname}/addressbook
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
	%dir %attr(0755,wwwrun,www) %{egwdatadir}/default
	%dir %attr(0755,wwwrun,www) %{egwdatadir}/default/files
	%dir %attr(0755,wwwrun,www) %{egwdatadir}/default/backup
	%config %attr(0640,wwwrun,www) %{egwdatadir}/header.inc.php
%endif
%if 0%{?rhel_version} || 0%{?fedora_version} || 0%{?centos_version}
	%dir %attr(0755,apache,apache) %{egwdatadir}/default
	%dir %attr(0755,apache,apache) %{egwdatadir}/default/files
	%dir %attr(0755,apache,apache) %{egwdatadir}/default/backup
	%config %attr(0640,apache,apache) %{egwdatadir}/header.inc.php
%endif
%if 0%{?mandriva_version}
	%dir %attr(0755,apache,apache) %{egwdatadir}/default
	%dir %attr(0755,apache,apache) %{egwdatadir}/default/files
	%dir %attr(0755,apache,apache) %{egwdatadir}/default/backup
	%config %attr(0640,apache,apache) %{egwdatadir}/header.inc.php
%endif

# addressbook is part of core now, as it contains required classes for accounts
#%files addressbook
#%defattr(-,root,root)
#%{prefix}/%{egwdirname}/addressbook

%files bookmarks
%defattr(-,root,root)
%{prefix}/%{egwdirname}/bookmarks

%files calendar
%defattr(-,root,root)
%{prefix}/%{egwdirname}/calendar

%files developer_tools
%defattr(-,root,root)
%{prefix}/%{egwdirname}/developer_tools

%files egw-pear
%defattr(-,root,root)
%{prefix}/%{egwdirname}/egw-pear

%files emailadmin
%defattr(-,root,root)
%{prefix}/%{egwdirname}/emailadmin

%files felamimail
%defattr(-,root,root)
%{prefix}/%{egwdirname}/felamimail

%files filemanager
%defattr(-,root,root)
%{prefix}/%{egwdirname}/filemanager

%files gallery
%defattr(-,root,root)
%{prefix}/%{egwdirname}/gallery

%files infolog
%defattr(-,root,root)
%{prefix}/%{egwdirname}/infolog

%files importexport
%defattr(-,root,root)
%{prefix}/%{egwdirname}/importexport

%files manual
%defattr(-,root,root)
%{prefix}/%{egwdirname}/manual

#%files mydms
#%defattr(-,root,root)
#%{prefix}/%{egwdirname}/mydms

%files news_admin
%defattr(-,root,root)
%{prefix}/%{egwdirname}/news_admin

%files notifications
%defattr(-,root,root)
%{prefix}/%{egwdirname}/notifications

%files phpbrain
%defattr(-,root,root)
%{prefix}/%{egwdirname}/phpbrain

%files phpsysinfo
%defattr(-,root,root)
%{prefix}/%{egwdirname}/phpsysinfo

%files polls
%defattr(-,root,root)
%{prefix}/%{egwdirname}/polls

%files projectmanager
%defattr(-,root,root)
%{prefix}/%{egwdirname}/projectmanager

%files registration
%defattr(-,root,root)
%{prefix}/%{egwdirname}/registration

%files resources
%defattr(-,root,root)
%{prefix}/%{egwdirname}/resources

%files sambaadmin
%defattr(-,root,root)
%{prefix}/%{egwdirname}/sambaadmin

%files sitemgr
%defattr(-,root,root)
%{prefix}/%{egwdirname}/sitemgr

%files syncml
%defattr(-,root,root)
%{prefix}/%{egwdirname}/syncml

%files stylite
%defattr(-,root,root)
%{prefix}/%{egwdirname}/stylite

%files timesheet
%defattr(-,root,root)
%{prefix}/%{egwdirname}/timesheet

%files tracker
%defattr(-,root,root)
%{prefix}/%{egwdirname}/tracker

%files wiki
%defattr(-,root,root)
%{prefix}/%{egwdirname}/wiki

#%files workflow
#%defattr(-,root,root)
#%{prefix}/%{egwdirname}/workflow

%changelog
* Mon Jun 1 2009 Ralf Becker <rb@stylite.de> 9.1.20090601
- EGroupware EPL release 9.1 preview
