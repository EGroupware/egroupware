%define packagename eGroupware
%define egwdirname egroupware
%define egwversion 1.6
%define packaging 003
#define epoch 1
%if 0%{?suse_version}
	%define php php5
	%define httpdconfd /etc/apache2/conf.d
	%define distribution SUSE Linux %{?suse_version}
	%define extra_requires apache2 apache2-mod_php5 php_any_db php5-dom php5-bz2 php5-openssl php5-zip php5-ctype
	%define cron cron
	%define apache_user wwwrun
	%define apache_group www
	%define pear_dir \\/usr\\/share\\/php5\\/PEAR
%else
	%define php php
	%define httpdconfd /etc/httpd/conf.d
	%define cron crontabs
	%define apache_user apache
	%define apache_group apache
	%define pear_dir \\/usr\\/share\\/pear
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
Source5: %{name}-rpmlintrc
Patch0: class.uiasyncservice.inc.php.patch
BuildRoot: /tmp/%{packagename}-buildroot
Requires: %{php} %{php}-mbstring %{php}-imap %{php}-gd %{php}-pear %{php}-posix %{extra_requires} %{cron} %{packagename}-egw-pear >= %{egwversion}.%{packaging}
Provides: egw-core egw-addressbook egw-etemplate
Requires: %{packagename}-core %{packagename}-bookmarks %{packagename}-calendar %{packagename}-developer_tools %{packagename}-emailadmin %{packagename}-felamimail %{packagename}-filemanager %{packagename}-infolog %{packagename}-importexport %{packagename}-manual %{packagename}-news_admin %{packagename}-notifications %{packagename}-phpbrain %{packagename}-polls %{packagename}-projectmanager %{packagename}-registration %{packagename}-resources %{packagename}-sambaadmin %{packagename}-sitemgr %{packagename}-syncml %{packagename}-timesheet %{packagename}-wiki
Obsoletes: %{packagename}-icalsrv
#otherwise build fails because of jar files in G2
BuildRequires: unzip

Prefix: /usr/share
Buildarch: noarch
AutoReqProv: no

Vendor: eGroupware
Packager: Ralf Becker <rb@stylite.de>

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
Summary: The eGroupware core package
Group: Web/Database
Provides: egw-core egw-etemplate egw-addressbook
%description core
This package provides the eGroupware core applications.
%post core
%if 0%{?rhel_version} || 0%{?fedora_version} || 0%{?centos_version}
	chcon -R -u user_u -r object_r -t httpd_sys_content_t /var/lib/egroupware
	setsebool -P httpd_can_network_connect=1
%endif

# addressbook is part of core now, as it contains required classes for accounts
#%package addressbook
#Version: %{egwversion}.%{packaging}
#Summary: The eGroupware addressbook application
#Group: Web/Database
#AutoReqProv: no
#Requires: egw-core >= %{egwversion}.%{packaging}
#Provides: egw-addressbook
#%description addressbook
#Contact manager with Vcard support.
#addressbook is the egroupware contact application.
#It has different backends to store and retrive contacts
#from SQL or LDAP.

%package bookmarks
Version: %{egwversion}.%{packaging}
Summary: The eGroupware bookmarks application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{egwversion}.%{packaging}
%description bookmarks
Manage your bookmarks with eGroupware. Has Netscape plugin.

%package calendar
Version: %{egwversion}.%{packaging}
Summary: The eGroupware calendar application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{egwversion}.%{packaging}
%description calendar
Powerful calendar with meeting request system, Alarms, ICal and E-Mail support,
and ACL security.

%package developer_tools
Version: %{egwversion}.%{packaging}
Summary: The eGroupware developer_tools application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{egwversion}.%{packaging}
%description developer_tools
The TranslationTools allow to create and extend translations-files for eGroupware.
They can search the sources for new / added phrases and show you the ones missing in your language.

%package egw-pear
Version: %{egwversion}.%{packaging}
Summary: The eGroupware egw-pear application
Group: Web/Database
Requires: %{php}-pear
AutoReqProv: no
Requires: egw-core >= %{egwversion}.%{packaging}
%description egw-pear
egw-pear contains modified pear classes necessary for eGroupware

%package emailadmin
Version: %{egwversion}.%{packaging}
Summary: The eGroupware emailadmin application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{egwversion}.%{packaging}, %{packagename}-egw-pear >= %{egwversion}.%{packaging}, php-openssl
%description emailadmin
EmailAdmin allow to maintain User email accounts

%package felamimail
Version: %{egwversion}.%{packaging}
Summary: The eGroupware Webmail application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{egwversion}.%{packaging}, %{packagename}-emailadmin >= %{egwversion}.%{packaging}, %{packagename}-egw-pear >= %{egwversion}.%{packaging}
%description felamimail
The Email application for eGroupware.

%package filemanager
Version: %{egwversion}.%{packaging}
Summary: The eGroupware filemanager application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{egwversion}.%{packaging}, egw-pear >= %{egwversion}.%{packaging}
%description filemanager
This is the filemanager app for eGroupware.

%package gallery
Version: %{egwversion}.%{packaging}
Summary: The eGroupware gallery application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{egwversion}.%{packaging}, egw-pear >= %{egwversion}.%{packaging}
%description gallery
An embedded Gallery2 for eGroupware.

%package icalsrv
Version: %{egwversion}.%{packaging}
Summary: The eGroupware icalsrv application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{egwversion}.%{packaging}
%description icalsrv
This is the old icalsrv app for eGroupware.
It is NOT necessary for GroupDAV, CalDAV or CardDAV,
which is build into the eGroupware core.

%package infolog
Version: %{egwversion}.%{packaging}
Summary: The eGroupware infolog application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{egwversion}.%{packaging}
%description infolog
This is the infolog app for eGroupware (Notes, ToDo, Phonelogs, CRM).

%package importexport
Version: %{egwversion}.%{packaging}
Summary: The eGroupware importexport application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{egwversion}.%{packaging}
%description importexport
This is the importexport app for eGroupware. It includes a comandline client.

%package manual
Version: %{egwversion}.%{packaging}
Summary: The eGroupware manual application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{egwversion}.%{packaging}
%description manual
This is the manual app for eGroupware: online help system.

%package mydms
Version: %{egwversion}.%{packaging}
Summary: The eGroupware mydms application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{egwversion}.%{packaging}, egw-pear >= %{egwversion}.%{packaging}
%description mydms
This is a mydms port to eGroupware.

%package news_admin
Version: %{egwversion}.%{packaging}
Summary: The eGroupware news_admin application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{egwversion}.%{packaging}
%description news_admin
This is the news_admin app for eGroupware.

%package notifications
Version: %{egwversion}.%{packaging}
Summary: The eGroupware notifications application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{egwversion}.%{packaging}
%description notifications
This is the notifications app for eGroupware.

%package phpbrain
Version: %{egwversion}.%{packaging}
Summary: The eGroupware phpbrain application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{egwversion}.%{packaging}
%description phpbrain
This is the phpbrain app for eGroupware.

%package phpsysinfo
Version: %{egwversion}.%{packaging}
Summary: The eGroupware phpsysinfo application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{egwversion}.%{packaging}
%description phpsysinfo
This is the phpsysinfo app for eGroupware.

%package polls
Version: %{egwversion}.%{packaging}
Summary: The eGroupware polls application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{egwversion}.%{packaging}
%description polls
This is the polls app for eGroupware.

%package projectmanager
Version: %{egwversion}.%{packaging}
Summary: The eGroupware projectmanager application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{egwversion}.%{packaging},
%description projectmanager
The projectmanager is eGroupware's new project management application.
It's fully integrated into eGroupware and use the data of InfoLog and Calendar.
Plugable datasources allow to support and manage further applications.

%package registration
Version: %{egwversion}.%{packaging}
Summary: The eGroupware registration application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{egwversion}.%{packaging}
%description registration
This is the registration app for eGroupware.

%package resources
Version: %{egwversion}.%{packaging}
Summary: The eGroupware resources application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{egwversion}.%{packaging}
%description resources
resources is a resource booking sysmtem for eGroupware.
Which integrates into the calendar.

%package sambaadmin
Version: %{egwversion}.%{packaging}
Summary: The eGroupware sambaadmin application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{egwversion}.%{packaging}
%description sambaadmin
Manage LDAP based sambaacounts and workstations.

%package sitemgr
Version: %{egwversion}.%{packaging}
Summary: The eGroupware Sitemanager CMS application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{egwversion}.%{packaging}
%description sitemgr
This is the Sitemanager CMS app for eGroupware.

%package syncml
Version: %{egwversion}.%{packaging}
Summary: The eGroupware syncml application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{egwversion}.%{packaging}
%description syncml
This is the syncml app for eGroupware.

%package timesheet
Version: %{egwversion}.%{packaging}
Summary: The eGroupware timesheet application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{egwversion}.%{packaging}
%description timesheet
Simple timesheet application, which allow to record and report
the times and other expenses. It can be uses as well standalone
as together with the ProjectManager application.

%package tracker
Version: %{egwversion}.%{packaging}
Summary: The eGroupware trouble ticket system application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{egwversion}.%{packaging}
%description tracker
This is the trouble ticket system app for eGroupware.

%package wiki
Version: %{egwversion}.%{packaging}
Summary: The eGroupware wiki application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{egwversion}.%{packaging},
%description wiki
This is the wiki app for eGroupware.

%prep
%setup0 -c -n %{egwdirname}
%setup1 -T -D -a 1 -n %{egwdirname}
%setup2 -T -D -a 2 -n %{egwdirname}
%setup3 -T -D -a 3 -n %{egwdirname}
%setup4 -T -D -a 4 -n %{egwdirname}
%patch0 -p 0

%build

%install
[ "%{buildroot}" != "/" ] && rm -rf %{buildroot}
mkdir -p $RPM_BUILD_ROOT%{egwdir}
mkdir -p $RPM_BUILD_ROOT%{httpdconfd}
sed 's/\/usr\/share\/pear/%{pear_dir}/' egroupware/doc/rpm-build/apache.conf > $RPM_BUILD_ROOT%{httpdconfd}/egroupware.conf
mkdir -p $RPM_BUILD_ROOT/etc/cron.d
sed 's/apache/%{apache_user}/' egroupware/doc/rpm-build/egroupware.cron > $RPM_BUILD_ROOT/etc/cron.d/egroupware
mkdir -p $RPM_BUILD_ROOT%{egwdatadir}/default/files
mkdir -p $RPM_BUILD_ROOT%{egwdatadir}/default/backup
cp egroupware/doc/rpm-build/header.inc.php $RPM_BUILD_ROOT%{egwdatadir}
cp -aRf egroupware/* $RPM_BUILD_ROOT%{egwdir}
cd %{buildroot}%{egwdir}
ln -s ../../..%{egwdatadir}/header.inc.php
# create symlink for suse to get scripts with /usr/bin/php working
%if 0%{?suse_version}
	#/usr/sbin/update-alternatives --install /usr/bin/php php /usr/bin/php5 99
	mkdir %{buildroot}/usr/bin
	cd %{buildroot}/usr/bin
	ln -s php5 php
%endif

%clean
[ "%{buildroot}" != "/" ] && rm -rf %{buildroot}

%post
%if 0%{?rhel_version} || 0%{?fedora_version} || 0%{?centos_version}
	chcon -R -u user_u -r object_r -t httpd_sys_content_t /var/lib/egroupware
	setsebool -P httpd_can_network_connect=1
%endif
%postun

# egroupware metapackage seems to need some files to be build ...
%files
%defattr(-,root,root)
%dir %{egwdir}
%dir %attr(0700,%{apache_user},%{apache_group}) %{egwdatadir}

%files core
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
	# symlink for suse to get scripts with /usr/bin/php working
	/usr/bin/php
%endif
%dir %attr(0700,%{apache_user},%{apache_group}) %{egwdatadir}
%dir %attr(0700,%{apache_user},%{apache_group}) %{egwdatadir}/default
%dir %attr(0700,%{apache_user},%{apache_group}) %{egwdatadir}/default/files
%dir %attr(0700,%{apache_user},%{apache_group}) %{egwdatadir}/default/backup
%config %attr(0640,%{apache_user},%{apache_group}) %{egwdatadir}/header.inc.php

# addressbook is part of core now, as it contains required classes for accounts
#%files addressbook
#%defattr(-,root,root)
#%{prefix}/%{egwdirname}/addressbook

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

%files icalsrv
%defattr(-,root,root)
%{prefix}/%{egwdirname}/icalsrv

%files infolog
%defattr(-,root,root)
%{prefix}/%{egwdirname}/infolog

%files importexport
%defattr(-,root,root)
%{prefix}/%{egwdirname}/importexport

%files manual
%defattr(-,root,root)
%{prefix}/%{egwdirname}/manual

%files mydms
%defattr(-,root,root)
%{prefix}/%{egwdirname}/mydms

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

%files timesheet
%defattr(-,root,root)
%{prefix}/%{egwdirname}/timesheet

%files tracker
%defattr(-,root,root)
%{prefix}/%{egwdirname}/tracker

%files wiki
%defattr(-,root,root)
%{prefix}/%{egwdirname}/wiki

%changelog
* Tue Mar 9 2010 Ralf Becker <rb@stylite.de> 1.6.003
- eGroupware 1.6.003 security and bugfix release
- fixes 2 security problems:
  + one is a serious remote command execution (allowing to run arbitrary
    command on the web server by simply issuing a HTTP request!)
  + the other a reflected cross-site scripting (XSS)
  Both require NOT a valid EGroupware account and work without being logged in!
- SyncML 1.2 support and many SyncML bug fixes
- many bugfixes since 1.6.002 release

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
