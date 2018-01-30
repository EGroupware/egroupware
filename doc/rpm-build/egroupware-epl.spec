Name: egroupware-epl
Version: 17.1.20180118
Release:
Summary: EGroupware is a web-based groupware suite written in php
Group: Web/Database
License: GPLv2 with exception of functions and esyncpro module, which is proprietary
URL: http://www.egroupware.org/EPL
Vendor: EGroupware GmbH, http://www.egroupware.org/
Packager: Ralf Becker <rb@egroupware.org>
Prefix: /usr/share
%define egwdir %{prefix}/egroupware
%define egwdatadir /var/lib/egroupware
%define egw_packagename eGroupware
%define egwdirname %{name}
#
# Define opensuse_version to tell opensuse 11.1 (1110) from sles11 (1110) and suse 10.1 from sles 10
%if "0%{?suse_version:1}%{!?sles_version:0}" == "010"
    %define opensuse_version %{suse_version}
%endif

%if 0%{?suse_version}
    %if 0%{?opensuse_version} < 1200
        %define php php5
    %else
        # opensuse 12+ uses /usr/bin/php again
        %define php php
    %endif

    %if 0%{?sles_version} < 1210
        %define php php
    %endif

	%define httpdconfd /etc/apache2/conf.d
	%define distribution SUSE Linux %{?suse_version}

    %if 0%{?sles_version}
        # sles 10, 11 does NOT contain libtidy, 11sp3 does not contain php5-posix
    	%define     extra_requires apache2 apache2-mod_php5 php_any_db %{php}-dom %{php}-bz2 %{php}-openssl %{php}-zip %{php}-ctype %{php}-sqlite %{php}-xml %{php}-xmlreader %{php}-xmlwriter %{php}-dom
    %else
        # SLES 12 and openSUSE Leap no longer sets sles_version, but suse_version == 1315: contains now php7 packages, but no php7-xml
        %if 0%{?suse_version} == 1315
        	%define php php7
    	    %define extra_requires apache2 apache2-mod_%{php} %{php}-opcache php_any_db %{php}-dom %{php}-bz2 %{php}-openssl %{php}-zip %{php}-ctype %{php}-sqlite %{php}-xmlreader %{php}-xmlwriter %{php}-dom %{php}-posix
        %else
      	    %define extra_requires apache2 apache2-mod_php5 %{php}-opcache php_any_db %{php}-dom %{php}-bz2 %{php}-openssl %{php}-zip %{php}-ctype %{php}-sqlite %{php}-xml %{php}-xmlreader %{php}-xmlwriter %{php}-dom %{php}-posix
        %endif
    %endif

	%define cron cron
	%define apache_user wwwrun
	%define apache_group www

# try fixing zypper does not require secondary dependency to egropware-epl-compat of following packages
#Suggests: %{name}-wiki            = %{version}
Suggests: %{name}-jdots           = %{version}
Suggests: %{name}-phpbrain        = %{version}
Suggests: %{name}-phpfreechat     = %{version}
Suggests: %{name}-sambaadmin      = %{version}
Suggests: %{name}-sitemgr         = %{version}
Recommends: %{php}-APCu

%else
	%define php php
	%define httpdconfd /etc/httpd/conf.d
	%define cron crontabs
	%define apache_user apache
	%define apache_group apache
%endif

%define install_log /root/%{name}-install.log
%define post_install /usr/bin/php %{egwdir}/doc/rpm-build/post_install.php --source_dir %{egwdir} --data_dir %{egwdatadir}
%if 0%{?fedora_version}
	%define osversion %{?fedora_version}
	%define distribution Fedora Core %{?fedora_version}
	%define extra_requires httpd php-mysqli php-xml php-tidy php-posix
%endif
%if 0%{?mandriva_version}
	%define osversion %{?mandriva_version}
	%define distribution Mandriva %{?mandriva_version}
	%define extra_requires apache php-mysql php-dom php-pdo_mysql php-pdo_sqlite php-tidy php-posix
# try to keep build from searching (for wrong) dependencys
	%undefine __find_provides
	%undefine __find_requires
%endif
%if 0%{?rhel_version}
	%define osversion %{?rhel_version}
	%define distribution Red Hat %{?rhel_version}
	%define extra_requires httpd php-mysqli php-xml php-tidy php-posix
%endif
%if 0%{?centos_version}
	%define osversion %{?centos_version}
	%define distribution CentOS %{?centos_version}
	%define extra_requires httpd php-mysqli php-xml php-tidy php-posix
%endif

Distribution: %{distribution}

Source0: %{name}-all-%{version}.tar.bz2
Source2: %{name}-functions-%{version}.tar.bz2
Source3: %{name}-archive-%{version}.tar.bz2
Source4: %{name}-esyncpro-%{version}.tar.bz2
#Source5: %{name}-contrib-%{version}.tar.gz
#Source6: phpfreechat_data_public.tar.gz
Source8: %{name}-rpmlintrc
#Source9: %{name}-gallery-%{version}.tar.bz2
Patch0: asyncservice.patch
#Patch1: revert-php-path-suse.patch
#Patch2: mandriva_upload_tmp_dir.patch
BuildRoot: %{_tmppath}/%{name}-buildroot

#otherwise build fails because of jar files in G2
BuildRequires: unzip sed

Buildarch: noarch
AutoReqProv: no

Requires: %{name}-core            = %{version}
Requires: %{name}-functions       = %{version}
Requires: %{name}-esync           = %{version}
Requires: %{name}-bookmarks       = %{version}
Requires: %{name}-calendar        = %{version}
Requires: %{name}-collabora       = %{version}
Requires: %{name}-filemanager     = %{version}
Requires: %{name}-infolog         = %{version}
Requires: %{name}-importexport    = %{version}
#Requires: %{name}-jdots           = %{version}
Requires: %{name}-mail            = %{version}
Requires: %{name}-news_admin      = %{version}
Requires: %{name}-notifications   = %{version}
#Requires: %{name}-phpbrain        = %{version}
#Requires: %{name}-phpfreechat     = %{version}
Requires: %{name}-projectmanager  = %{version}
Requires: %{name}-registration    = %{version}
Requires: %{name}-resources       = %{version}
#Requires: %{name}-sambaadmin      = %{version}
#Requires: %{name}-sitemgr         = %{version}
Requires: %{name}-timesheet       = %{version}
Requires: %{name}-tracker         = %{version}
Requires: %{name}-vendor          = %{version}
#Requires: %{name}-wiki            = %{version}
Obsoletes: %{egw_packagename}
Obsoletes: %{egw_packagename}-core
Obsoletes: %{egw_packagename}-egw-pear
Obsoletes: %{egw_packagename}-esync
Obsoletes: %{egw_packagename}-addressbook
Obsoletes: %{egw_packagename}-bookmarks
Obsoletes: %{egw_packagename}-calendar
Obsoletes: %{egw_packagename}-developer_tools
Obsoletes: %{egw_packagename}-emailadmin
Obsoletes: %{egw_packagename}-felamimail
Obsoletes: %{egw_packagename}-filemanager
Obsoletes: %{egw_packagename}-infolog
Obsoletes: %{egw_packagename}-importexport
Obsoletes: %{egw_packagename}-manual
Obsoletes: %{egw_packagename}-news_admin
Obsoletes: %{egw_packagename}-notifications
Obsoletes: %{egw_packagename}-phpbrain
Obsoletes: %{egw_packagename}-phpfreechat
Obsoletes: %{egw_packagename}-phpsysinfo
Obsoletes: %{egw_packagename}-polls
Obsoletes: %{egw_packagename}-projectmanager
Obsoletes: %{egw_packagename}-registration
Obsoletes: %{egw_packagename}-resources
Obsoletes: %{egw_packagename}-sambaadmin
Obsoletes: %{egw_packagename}-sitemgr
Obsoletes: %{egw_packagename}-timesheet
Obsoletes: %{egw_packagename}-tracker
Obsoletes: %{egw_packagename}-wiki
# packages no longer in 14.1
Obsoletes: %{name}-felamimail
Obsoletes: %{name}-syncml
Obsoletes: %{name}-phpsysinfo
Obsoletes: %{name}-polls
Obsoletes: %{egw_packagename}-felamimail
Obsoletes: %{egw_packagename}-syncml
Obsoletes: %{egw_packagename}-phpsysinfo
Obsoletes: %{egw_packagename}-polls
# packages no longer in 14.2
Obsoletes: %{name}-egw-pear
# packages no longer in 14.3
Obsoletes: %{name}-manual
Obsoletes: %{name}-developer_tools

%post
# Check binary paths and create links for opensuse/sles
# create symlink for suse to get scripts with /usr/bin/php working
%if 0%{?suse_version}
    if [ ! -f /usr/bin/php -a -x /usr/bin/php5 ]; then \
        echo "Installing php -> php5 alternative"; \
        /usr/sbin/update-alternatives --install /usr/bin/php php /usr/bin/php5 99; \
    fi
%endif
%if 0%{?rhel_version} || 0%{?fedora_version} || 0%{?centos_version}
    if [ $(getenforce) != "Disabled" ]
    then
	   chcon -R -u user_u -r object_r -t httpd_sys_content_t %{egwdatadir}
	   setsebool -P httpd_can_network_connect=1
    fi
%endif
/bin/date >> %{install_log}
%{post_install} 2>&1 | tee -a %{install_log}
# fix ownership of files created during update
/bin/chown -R %{apache_user} /var/lib/egroupware
echo "EGroupware install log saved to %{install_log}"

%description
EGroupware is a web-based groupware suite written in PHP.

EGroupware EPL combines EGroupware GmbH actual EGroupware enhancements and the recent development of the EGroupware open source project in one software package.
- Brand new EPL features, which are not available publicly in the community edition of EGroupware
- The latest possible state of open source community features.

This package automatically requires the EGroupware default applications:

egroupware core with: admin, api, docs, prefereces and setup,
addressbook, bookmarks, calendar, mail,
filemanager, infolog, news admin, knowledgebase
projectmanager, resources, sambaadmin, sitemgr, eSync, timesheet, tracker, wiki

It also provides an API for developing additional applications.

Further contributed applications are available as separate packages.

%package core
Summary: The EGroupware core
Group: Web/Database
Requires: %{php} >= 5.6.0
Requires: %{php}-mbstring %{php}-gd %{extra_requires} %{cron} zip %{php}-json %{php}-xsl
Provides: egw-core %{version}
Provides: egw-addressbook %{version}
%if 0%{?suse_version}
Provides: /usr/bin/php
%endif
Obsoletes: %{egw_packagename}-core
Obsoletes: %{egw_packagename}-addressbook
Obsoletes: %{name}-emailadmin
Obsoletes: %{name}-pixelegg
%description core
This package provides the EGroupware core applications
(API, admin, etemplate, preferences and setup) plus addressbook.

%package compat
Summary: Compatibility to old api
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{version}
Provides: egw-compat %{version}
Provides: egw-etemplate %{version}
%description compat
This package provides compatibility to old / pre 16.1 EGroupware api.
So to speak the phpgwapi and etemplate directories.

%post compat
# install phpgwapi&etemplate, "$1" = "1" means install, not update, as there will be no more updates
if [ "$1" = "1" ]
then
	%{post_install} --install-update-app phpgwapi 2>&1 | tee -a %{install_log}
	%{post_install} --install-update-app etemplate 2>&1 | tee -a %{install_log}
fi

%package esync
Version: %{version}
Summary: The EGroupware eSync application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{version}
Obsoletes: %{egw_packagename}-esync
%description esync
Z-Push based ActiveSync protocol implementation.

%package archive
Version: %{version}
Summary: Benno Mail Archive integration
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{version}
Obsoletes: %{egw_packagename}-archive
%description archive
Shows (not included) Benno Mail Archive inside EGroupware and logs you in automatic.

%post archive
# update/install archive
%{post_install} --install-update-app archive 2>&1 | tee -a %{install_log}

%package bookmarks
Version: %{version}
Summary: The EGroupware bookmarks application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{version}
Obsoletes: %{egw_packagename}-bookmarks
%description bookmarks
Manage your bookmarks with EGroupware. Has Netscape plugin.

%package calendar
Version: %{version}
Summary: The EGroupware calendar application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{version}
Obsoletes: %{egw_packagename}-calendar
%description calendar
Powerful calendar with meeting request system, Alarms, ICal and E-Mail support,
and ACL security.

%package collabora
Version: %{version}
Summary: The EGroupware Collabora integration
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{version}
Obsoletes: %{egw_packagename}-collabora
%description collabora
Integrates Collabora Libre Office Online into EGroupware.

%package mail
Version: %{version}
Summary: The EGroupware Webmail application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{version}
Obsoletes: %{egw_packagename}-felamimail
Obsoletes: %{name}-felamimail
%description mail
The Email application for EGroupware.

%package filemanager
Version: %{version}
Summary: The EGroupware filemanager application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{version}
Obsoletes: %{egw_packagename}-filemanager
%description filemanager
This is the filemanager app for EGroupware.

%package infolog
Version: %{version}
Summary: The EGroupware infolog application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{version}
Obsoletes: %{egw_packagename}-infolog
%description infolog
This is the infolog app for EGroupware (Notes, ToDo, Phonelogs, CRM).

%package importexport
Version: %{version}
Summary: The EGroupware importexport application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{version}
Obsoletes: %{egw_packagename}-importexport
%description importexport
This is the importexport app for EGroupware. It includes a comandline client.

%package jdots
Version: %{version}
Summary: Old tab-based EPL template based on idots look
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{version}
Requires: egw-compat >= %{version}
%description jdots
EPL 11.1 default template.

%package news_admin
Version: %{version}
Summary: The EGroupware news_admin application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{version}
Obsoletes: %{egw_packagename}-news_admin
%description news_admin
This is the news_admin app for EGroupware.

%package notifications
Version: %{version}
Summary: The EGroupware notifications application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{version}
Obsoletes: %{egw_packagename}-notifications
%description notifications
This is the notifications app for EGroupware.

%package phpbrain
Version: %{version}
Summary: The EGroupware phpbrain application
Group: Web/Database
AutoReqProv: no
Requires: egw-compat >= %{version}
Obsoletes: %{egw_packagename}-phpbrain
%description phpbrain
This is a knowledgebase for EGroupware.

%post phpbrain
# update/install phpbrain, as no longer installed by default
%{post_install} --install-update-app phpbrain 2>&1 | tee -a %{install_log}

%package phpfreechat
Version: %{version}
Summary: The EGroupware chat application
Group: Web/Database
AutoReqProv: no
Requires: egw-compat >= %{version}
# phpFreeChat does NOT work with php7
Requires: %{php} < 7.0.0
Obsoletes: %{egw_packagename}-phpfreechat
%description phpfreechat
Chat with other EGroupware users. A port of phpFreeChat for EGroupware.

%post phpfreechat
# update/install phpfreechat, as no longer installed by default
%{post_install} --install-update-app phpfreechat 2>&1 | tee -a %{install_log}

%package projectmanager
Version: %{version}
Summary: The EGroupware projectmanager application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{version},
Obsoletes: %{egw_packagename}-projectmanager
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
Obsoletes: %{egw_packagename}-registration
%description registration
This is the registration app for EGroupware.

%package resources
Version: %{version}
Summary: The EGroupware resources application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{version}
Obsoletes: %{egw_packagename}-resources
%description resources
resources is a resource booking sysmtem for EGroupware.
Which integrates into the calendar.

%package sambaadmin
Version: %{version}
Summary: The EGroupware sambaadmin application
Group: Web/Database
AutoReqProv: no
Requires: egw-compat >= %{version}
Obsoletes: %{egw_packagename}-sambaadmin
%description sambaadmin
Manage LDAP based sambaacounts and workstations for Samba3.

%post sambaadmin
# update/install sambaadmin, as no longer installed by default
%{post_install} --install-update-app sambaadmin 2>&1 | tee -a %{install_log}

%package sitemgr
Version: %{version}
Summary: The EGroupware Sitemanager CMS application
Group: Web/Database
AutoReqProv: no
Requires: egw-compat >= %{version}
Obsoletes: %{egw_packagename}-sitemgr
%description sitemgr
This is the Sitemanager CMS app for EGroupware.

%post sitemgr
# update/install sitemgr, as no longer installed by default
%{post_install} --install-update-app sitemgr 2>&1 | tee -a %{install_log}

%package functions
Version: %{version}
Summary: EGroupware EPL functions
License: proprietary, see http://www.egroupware.org/EPL
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{version}
Obsoletes: %{name}-groups %{name}-stylite
%description functions
The package contains EGroupware GmbH's proprietary EPL enhancements:
- enhanced links stream wrapper allows browsing of app directories
- filemanger favorites

%package timesheet
Version: %{version}
Summary: The EGroupware timesheet application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{version}
Obsoletes: %{egw_packagename}-timesheet
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
Obsoletes: %{egw_packagename}-tracker
%description tracker
This is the trouble ticket system app for EGroupware.

%package wiki
Version: %{version}
Summary: The EGroupware wiki application
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{version},
Obsoletes: %{egw_packagename}-wiki
%description wiki
This is the wiki app for EGroupware.

%post wiki
# update/install wiki, as no longer installed by default
%{post_install} --install-update-app wiki 2>&1 | tee -a %{install_log}

%package vendor
Version: %{version}
Summary: External EGroupware dependencies
Group: Web/Database
AutoReqProv: no
%description vendor
Dependencies have been installed using Composer.
With this package EGroupware no longer depends on PEAR.
Dependencies include:
- diverse Horde framework packages like Horde_Imap_Client
- some PEAR packages incl. PEAR itself

%package esyncpro
Version: %{version}
Summary: EGroupware EPL eSync Provisioning
License: proprietary
Group: Web/Database
AutoReqProv: no
Requires: egw-core >= %{version}, %{name}-esync >= %{version}
%description esyncpro
EGroupware's eSync Provisioning app allows to edit and assign
policies to devices and keeps a central list of syncing devices.
It also allows to remote wipe or view sync logs of all devices.

%post esyncpro
# update/install esyncpro
%{post_install} --install-update-app esyncpro 2>&1 | tee -a %{install_log}

%prep
echo "Detected php: %{php}"
echo "post_install: %{post_install}"
%setup0 -c -n %{egwdirname}
#%setup1 -T -D -a 1 -n %{egwdirname}
%setup2 -T -D -a 2 -n %{egwdirname}
%setup3 -T -D -a 3 -n %{egwdirname}
%setup4 -T -D -a 4 -n %{egwdirname}
#%setup5 -T -D -a 5 -n %{egwdirname}
#%setup6 -T -D -a 6 -n %{egwdirname}
#%setup9 -T -D -a 9 -n %{egwdirname}
%patch0 -p 0
#%patch1 -p 0
#%patch2 -p 0

%build

%install
[ "%{buildroot}" != "/" ] && rm -rf %{buildroot}
mkdir -p $RPM_BUILD_ROOT%{egwdir}
mkdir -p $RPM_BUILD_ROOT%{httpdconfd}
cp egroupware/doc/rpm-build/apache.conf $RPM_BUILD_ROOT%{httpdconfd}/egroupware.conf
%if 0%{?suse_version}
# RHEL/CentOS needs open_basedir to include /etc/pki/tls/certs:/etc/pki/ca-trust
# SUSE uses /var/lib/ca-certificates/openssl instead for trusted OpenSSL CA
    sed -i 's|/etc/pki/tls/certs:/etc/pki/ca-trust|/var/lib/ca-certificates/openssl|g' $RPM_BUILD_ROOT%{httpdconfd}/egroupware.conf
%endif
mkdir -p $RPM_BUILD_ROOT/etc/cron.d
sed 's/apache/%{apache_user}/' egroupware/doc/rpm-build/egroupware.cron > $RPM_BUILD_ROOT/etc/cron.d/egroupware
mkdir -p $RPM_BUILD_ROOT%{egwdatadir}/default/files
mkdir -p $RPM_BUILD_ROOT%{egwdatadir}/default/backup
cp egroupware/doc/rpm-build/header.inc.php $RPM_BUILD_ROOT%{egwdatadir}
cp -aRf egroupware/* $RPM_BUILD_ROOT%{egwdir}
cd %{buildroot}%{egwdir}
ln -s ../../..%{egwdatadir}/header.inc.php

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
%{egwdir}/composer.json
%{egwdir}/composer.lock
%{egwdir}/header.inc.php
%{egwdir}/header.inc.php.template
%{egwdir}/index.php
%{egwdir}/json.php
%{egwdir}/login.php
%{egwdir}/logout.php
%{egwdir}/redirect.php
%{egwdir}/remote.php
%{egwdir}/share.php
%{egwdir}/status.php
%{egwdir}/groupdav.php
%{egwdir}/README.md
%{egwdir}/package.json
%{egwdir}/Gruntfile.js
%{egwdir}/updateGruntfile.php
%{egwdir}/groupdav.htaccess
%{egwdir}/webdav.php
%{egwdir}/addressbook
%{egwdir}/admin
%{egwdir}/api
%{egwdir}/doc
%{egwdir}/emailadmin
%{egwdir}/files
%{egwdir}/home
%{egwdir}/pixelegg
%{egwdir}/preferences
%{egwdir}/setup
%{egwdir}/ViewerJS
%config(noreplace) %attr(0644,root,root) /etc/cron.d/egroupware
%config(noreplace) %attr(0644,root,root) %{httpdconfd}/egroupware.conf
%if 0%{?suse_version}
	%dir %attr(0755,root,root) /etc/apache2
	%dir %attr(0755,root,root) %{httpdconfd}
%endif
%dir %attr(0700,%{apache_user},%{apache_group}) %{egwdatadir}
%dir %attr(0700,%{apache_user},%{apache_group}) %{egwdatadir}/default
%dir %attr(0700,%{apache_user},%{apache_group}) %{egwdatadir}/default/files
%dir %attr(0700,%{apache_user},%{apache_group}) %{egwdatadir}/default/backup
%config(noreplace) %attr(0640,%{apache_user},%{apache_group}) %{egwdatadir}/header.inc.php

%files compat
%defattr(-,root,root)
%{egwdir}/phpgwapi
%{egwdir}/etemplate

%files archive
%defattr(-,root,root)
%{egwdir}/archive

%files bookmarks
%defattr(-,root,root)
%{egwdir}/bookmarks

%files esync
%defattr(-,root,root)
%{egwdir}/activesync

%files esyncpro
%defattr(-,root,root)
%{egwdir}/esyncpro

%files calendar
%defattr(-,root,root)
%{egwdir}/calendar

%files collabora
%defattr(-,root,root)
%{egwdir}/collabora

%files filemanager
%defattr(-,root,root)
%{egwdir}/filemanager

%files infolog
%defattr(-,root,root)
%{egwdir}/infolog

%files importexport
%defattr(-,root,root)
%{egwdir}/importexport

%files jdots
%defattr(-,root,root)
%{egwdir}/jdots

%files mail
%defattr(-,root,root)
%{egwdir}/mail

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

%files functions
%defattr(-,root,root)
%{egwdir}/stylite

%files timesheet
%defattr(-,root,root)
%{egwdir}/timesheet

%files tracker
%defattr(-,root,root)
%{egwdir}/tracker

%files vendor
%defattr(-,root,root)
%{egwdir}/vendor

%files wiki
%defattr(-,root,root)
%{egwdir}/wiki
