Name: eGroupware
Version: 1.8.002.20111111
Release: 1
Summary: EGroupware is a web-based groupware suite written in php.
Group: Web/Database
License: GPLv2
URL: http://www.egroupware.org
Vendor: Stylite GmbH, http://www.stylite.de/
Packager: Ralf Becker <rb@stylite.de>
Prefix: /usr/share
%define egwdir %{prefix}/egroupware
%define egwdatadir /var/lib/egroupware

%if 0%{?suse_version}
	%define php php5
	%define httpdconfd /etc/apache2/conf.d
	%define distribution SUSE Linux %{?suse_version}
	%define extra_requires apache2 apache2-mod_php5 php_any_db php5-dom php5-bz2 php5-openssl php5-zip php5-ctype php5-sqlite
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
%define install_log /root/%{name}-install.log
%define post_install /usr/bin/%{php} %{egwdir}/doc/rpm-build/post_install.php --source_dir %{egwdir} --data_dir %{egwdatadir}
%if 0%{?fedora_version}
	%define osversion %{?fedora_version}
	%define distribution Fedora Core %{?fedora_version}
	%define extra_requires httpd php-mysql php-xml
%endif
%if 0%{?mandriva_version}
	%define osversion %{?mandriva_version}
	%define distribution Mandriva %{?mandriva_version}
	%define extra_requires apache php-mysql php-dom php-pdo_mysql php-pdo_sqlite
# try to keep build from searching (for wrong) dependencys
	%undefine __find_provides
	%undefine __find_requires
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

Distribution: %{distribution}

Source0: %{name}-%{version}.tar.gz
Source1: %{name}-egw-pear-%{version}.tar.bz2
Source2: %{name}-phpfreechat-%{version}.tar.bz2
Source3: %{name}-gallery-%{version}.tar.bz2
Source4: phpfreechat_data_public.tar.gz
Source5: debian.changes
Source6: %{name}-rpmlintrc
Patch0: class.uiasyncservice.inc.php.patch
BuildRoot: %{_tmppath}/%{name}-buildroot

#otherwise build fails because of jar files in G2
BuildRequires: unzip sed

Buildarch: noarch
AutoReqProv: no

Requires: %{name}-core            >= %{version}
Requires: %{name}-egw-pear        >= %{version}
#Requires: %{name}-addressbook    >= %{version}
Requires: %{name}-bookmarks       >= %{version}
Requires: %{name}-calendar        >= %{version}
Requires: %{name}-developer_tools >= %{version}
Requires: %{name}-emailadmin      >= %{version}
Requires: %{name}-felamimail      >= %{version}
Requires: %{name}-filemanager     >= %{version}
Requires: %{name}-infolog         >= %{version}
Requires: %{name}-importexport    >= %{version}
Requires: %{name}-manual          >= %{version}
Requires: %{name}-news_admin      >= %{version}
Requires: %{name}-notifications   >= %{version}
Requires: %{name}-phpbrain        >= %{version}
Requires: %{name}-phpfreechat     >= %{version}
Requires: %{name}-phpsysinfo      >= %{version}
Requires: %{name}-polls           >= %{version}
Requires: %{name}-projectmanager  >= %{version}
Requires: %{name}-registration    >= %{version}
Requires: %{name}-resources       >= %{version}
Requires: %{name}-sambaadmin      >= %{version}
Requires: %{name}-sitemgr         >= %{version}
Requires: %{name}-syncml          >= %{version}
Requires: %{name}-timesheet       >= %{version}
Requires: %{name}-tracker         >= %{version}
Requires: %{name}-wiki            >= %{version}
%post
%if 0%{?rhel_version} || 0%{?fedora_version} || 0%{?centos_version}
	chcon -R -u user_u -r object_r -t httpd_sys_content_t %{egwdatadir}
	setsebool -P httpd_can_network_connect=1
%endif
/bin/date >> %{install_log}
%{post_install} 2>&1 | tee -a %{install_log}
echo "EGroupware install log saved to %{install_log}"

%description
EGroupware is a web-based groupware suite written in PHP.

This package automatically requires the EGroupware default applications:

egroupware core with: admin, api, docs, etemplate, prefereces and setup,
addressbook, bookmarks, calendar, translation-tools, emailadmin, felamimail,
filemanager, infolog, manual, news admin, knowledgebase, polls, phpfreechat,
projectmanager, resources, sambaadmin, sitemgr, syncml, timesheet, tracker, wiki

It also provides an API for developing additional applications.

Further contributed applications are available as separate packages.

%package core
Summary: The EGroupware core
Group: Web/Database
Requires: %{php} >= 5.2.1
Requires: %{php}-mbstring %{php}-gd %{php}-mcrypt %{php}-pear %{php}-posix %{extra_requires} %{cron} zip
Provides: egw-core %{version}
Provides: egw-etemplate %{version}
Provides: egw-addressbook %{version}
Obsoletes: %{name}-addressbook
%description core
This package provides the EGroupware core applications
(API, admin, etemplate, preferences and setup) plus addressbook.

%package egw-pear
Version: %{version}
Summary: The EGroupware egw-pear application
Group: Web/Database
Requires: %{php}-pear
AutoReqProv: no
Requires: egw-core >= %{version}
Provides: egw-pear %{version}
%description egw-pear
egw-pear contains modified pear classes necessary for EGroupware

# addressbook is part of core now, as it contains required classes for accounts
#%package addressbook
#Version: %{version}
#Summary: The EGroupware addressbook application
#Group: Web/Database
#AutoReqProv: no
#Requires: egw-core >= %{version}
#%description addressbook
#Contact manager with Vcard support.
#Addressbook is the egroupware contact application.
#It has different backends to store and retrive contacts
#from SQL or LDAP.

%package bookmarks
Version: %{version}
Summary: The EGroupware bookmarks application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{version}
%description bookmarks
Manage your bookmarks with EGroupware. Has Netscape plugin.

%package calendar
Version: %{version}
Summary: The EGroupware calendar application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{version}
%description calendar
Powerful calendar with meeting request system, Alarms, ICal and E-Mail support,
and ACL security.

%package developer_tools
Version: %{version}
Summary: The EGroupware developer_tools application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{version}
%description developer_tools
The TranslationTools allow to create and extend translations-files for EGroupware.
They can search the sources for new / added phrases and show you the ones missing in your language.

%package emailadmin
Version: %{version}
Summary: The EGroupware emailadmin application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{version}
Requires: %{php}-imap
Requires: %{name}-egw-pear >= %{version}
%description emailadmin
EmailAdmin allow to maintain User email accounts

%package felamimail
Version: %{version}
Summary: The EGroupware Webmail application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{version}
Requires: %{name}-emailadmin >= %{version}
Requires: %{name}-egw-pear >= %{version}
Requires: tnef
%description felamimail
The Email application for EGroupware.

%package filemanager
Version: %{version}
Summary: The EGroupware filemanager application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{version}
Requires: %{name}-egw-pear >= %{version}
%description filemanager
This is the filemanager app for EGroupware.

%package gallery
Version: %{version}
Summary: The EGroupware gallery application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{version}
%description gallery
An embedded Gallery2 for EGroupware.

%package infolog
Version: %{version}
Summary: The EGroupware infolog application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{version}
%description infolog
This is the infolog app for EGroupware (Notes, ToDo, Phonelogs, CRM).

%package importexport
Version: %{version}
Summary: The EGroupware importexport application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{version}
%description importexport
This is the importexport app for EGroupware. It includes a comandline client.

%package manual
Version: %{version}
Summary: The EGroupware manual application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{version}
Requires: %{name}-wiki >= %{version}
%description manual
This is the manual app for EGroupware: online help system.

%package news_admin
Version: %{version}
Summary: The EGroupware news_admin application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{version}
%description news_admin
This is the news_admin app for EGroupware.

%package notifications
Version: %{version}
Summary: The EGroupware notifications application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{version}
%description notifications
This is the notifications app for EGroupware.

%package phpbrain
Version: %{version}
Summary: The EGroupware phpbrain application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{version}
%description phpbrain
This is a knowledgebase for EGroupware.

%package phpfreechat
Version: %{version}
Summary: The EGroupware chat application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{version}
%description phpfreechat
Chat with other EGroupware users. A port of phpFreeChat for EGroupware.

%package phpsysinfo
Version: %{version}
Summary: The EGroupware phpsysinfo application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{version}
%description phpsysinfo
This is the phpsysinfo app for EGroupware.

%package polls
Version: %{version}
Summary: The EGroupware polls application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{version}
%description polls
This is the polls app for EGroupware.

%package projectmanager
Version: %{version}
Summary: The EGroupware projectmanager application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{version},
Requires: jpgraph >= 1.13
%description projectmanager
The projectmanager is EGroupware's new project management application.
It's fully integrated into EGroupware and use the data of InfoLog and Calendar.
Plugable datasources allow to support and manage further applications.

%package registration
Version: %{version}
Summary: The EGroupware registration application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{version}
%description registration
This is the registration app for EGroupware.

%package resources
Version: %{version}
Summary: The EGroupware resources application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{version}
%description resources
resources is a resource booking sysmtem for EGroupware.
Which integrates into the calendar.

%package sambaadmin
Version: %{version}
Summary: The EGroupware sambaadmin application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{version}
%description sambaadmin
Manage LDAP based sambaacounts and workstations.

%package sitemgr
Version: %{version}
Summary: The EGroupware Sitemanager CMS application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{version}
%description sitemgr
This is the Sitemanager CMS app for EGroupware.

%package syncml
Version: %{version}
Summary: The EGroupware syncml application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{version}
Requires: %{name}-egw-pear >= %{version}
%description syncml
This is the syncml app for EGroupware.

%package timesheet
Version: %{version}
Summary: The EGroupware timesheet application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{version}
%description timesheet
Simple timesheet application, which allow to record and report
the times and other expenses. It can be uses as well standalone
as together with the ProjectManager application.

%package tracker
Version: %{version}
Summary: The EGroupware trouble ticket system application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{version}
%description tracker
This is the trouble ticket system app for EGroupware.

%package wiki
Version: %{version}
Summary: The EGroupware wiki application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{version},
%description wiki
This is the wiki app for EGroupware.

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
# copy current changelog to doc/rpm-build
install -m 444 %{SOURCE5} $RPM_BUILD_ROOT%{egwdir}/doc/rpm-build

%clean
[ "%{buildroot}" != "/" ] && rm -rf %{buildroot}

# egroupware metapackage seems to need some files to be build ...
%files
%defattr(-,root,root)
%dir %{egwdir}
%dir %attr(0700,%{apache_user},%{apache_group}) %{egwdatadir}

%files core
%defattr(-,root,root)
%dir %{egwdir}
%{egwdir}/about.php
%{egwdir}/anon_wrapper.php
%{egwdir}/header.inc.php
%{egwdir}/header.inc.php.template
%{egwdir}/index.php
%{egwdir}/json.php
%{egwdir}/login.php
%{egwdir}/logout.php
%{egwdir}/redirect.php
%{egwdir}/rpc.php
%{egwdir}/set_box.php
%{egwdir}/soap.php
%{egwdir}/svn-helper.php
%{egwdir}/xajax.php
%{egwdir}/xmlrpc.php
%{egwdir}/groupdav.php
%{egwdir}/groupdav.htaccess
%{egwdir}/webdav.php
%{egwdir}/addressbook
%{egwdir}/admin
%{egwdir}/doc
%{egwdir}/etemplate
%{egwdir}/home
%{egwdir}/phpgwapi
%{egwdir}/preferences
%{egwdir}/setup
%attr(0644,root,root) /etc/cron.d/egroupware
%config(noreplace) %attr(0644,root,root) %{httpdconfd}/egroupware.conf
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
%config(noreplace) %attr(0640,%{apache_user},%{apache_group}) %{egwdatadir}/header.inc.php

# addressbook is part of core now, as it contains required classes for accounts
#%files addressbook
#%defattr(-,root,root)
#%{egwdir}/addressbook

%files bookmarks
%defattr(-,root,root)
%{egwdir}/bookmarks

%files calendar
%defattr(-,root,root)
%{egwdir}/calendar

%files developer_tools
%defattr(-,root,root)
%{egwdir}/developer_tools

%files egw-pear
%defattr(-,root,root)
%{egwdir}/egw-pear

%files emailadmin
%defattr(-,root,root)
%{egwdir}/emailadmin

%files felamimail
%defattr(-,root,root)
%{egwdir}/felamimail

%files filemanager
%defattr(-,root,root)
%{egwdir}/filemanager

%files gallery
%defattr(-,root,root)
%{egwdir}/gallery

%files infolog
%defattr(-,root,root)
%{egwdir}/infolog

%files importexport
%defattr(-,root,root)
%{egwdir}/importexport

%files manual
%defattr(-,root,root)
%{egwdir}/manual

%files news_admin
%defattr(-,root,root)
%{egwdir}/news_admin

%files notifications
%defattr(-,root,root)
%{egwdir}/notifications

%files phpbrain
%defattr(-,root,root)
%{egwdir}/phpbrain

%files phpfreechat
%defattr(-,root,root)
%{egwdir}/phpfreechat

%files phpsysinfo
%defattr(-,root,root)
%{egwdir}/phpsysinfo

%files polls
%defattr(-,root,root)
%{egwdir}/polls

%files projectmanager
%defattr(-,root,root)
%{egwdir}/projectmanager

%files registration
%defattr(-,root,root)
%{egwdir}/registration

%files resources
%defattr(-,root,root)
%{egwdir}/resources

%files sambaadmin
%defattr(-,root,root)
%{egwdir}/sambaadmin

%files sitemgr
%defattr(-,root,root)
%{egwdir}/sitemgr

%files syncml
%defattr(-,root,root)
%{egwdir}/syncml

%files timesheet
%defattr(-,root,root)
%{egwdir}/timesheet

%files tracker
%defattr(-,root,root)
%{egwdir}/tracker

%files wiki
%defattr(-,root,root)
%{egwdir}/wiki
