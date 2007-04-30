%define packagename eGroupWare
%define egwdirname egroupware
%define egwversion 1.3
%define packaging 021
#%define epoch 1
%if 0%{?suse_version}
	%define httpdroot /srv/www/htdocs
	%define httpdconfd /etc/apache2/conf.d
	%define source5 egroupware_suse.tar.bz2
	%define distribution SUSE Linux %{?suse_version}
%endif
%if 0%{?fedora_version}
	%define httpdroot /var/www/html
	%define httpdconfd /etc/httpd/conf.d
	%define osversion %{?fedora_version}
	%define source5 egroupware_fedora.tar.bz2
	%define distribution Fedora Core %{?suse_version}
%endif

%define addressbook addressbook
%define bookmarks bookmarks
%define calendar calendar
%define developer_tools developer_tools
%define egw-pear egw-pear
%define emailadmin emailadmin
%define egwical egwical
%define felamimail felamimail
%define filemanager filemanager
%define gallery gallery
%define icalsrv icalsrv
%define infolog infolog
%define manual manual
%define messenger messenger
%define mydms mydms
%define news_admin news_admin
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
Summary: eGroupWare is a web-based groupware suite written in php.
Distribution: %{distribution}
                                                                                                                             
Group: Web/Database
License: GPL/LGPL
URL: http://www.egroupware.org/
Source0: %{packagename}-%{egwversion}.%{packaging}.tar.bz2
Source1: %{packagename}-egw-pear-%{egwversion}.%{packaging}.tar.bz2
Source2: %{packagename}-icalsrv-%{egwversion}.%{packaging}.tar.bz2
Source3: %{packagename}-egwical-%{egwversion}.%{packaging}.tar.bz2
Source4: %{packagename}-gallery-%{egwversion}.%{packaging}.tar.bz2
Source5: %{?source5}
#Patch0: manageheader.php.patch
#Patch1: class.uiasyncservice.inc.php.patch
BuildRoot: /tmp/%{packagename}-buildroot
Requires: php5 php5-mbstring php5-imap php5-gd apache2-mod_php5 php5-pear cron
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

%package core
Summary: The eGroupWare contrib package
Group: Web/Database
Requires: php5 php5-mbstring php5-imap php5-gd php5-pear apache2-mod_php5 cron
Provides: egw-core
Conflicts: %{packagename}
%description core
This package provides the eGroupWare contrib applications.

%package %{addressbook}
Version: %{egwversion}.%{packaging}
Summary: The eGroupWare %{addressbook} application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core = %{egwversion}.%{packaging}
Provides: egw-%{addressbook}
%description %{addressbook}
Contact manager with Vcard support.
%{addressbook} is the egroupware default contact application.
It makes use of the egroupware contacts class to store and retrieve 
contact information via SQL, LDAP or Active Directory.

%package %{bookmarks}
Version: %{egwversion}.%{packaging}
Summary: The eGroupWare %{bookmarks} application
Group: Web/Database
AutoReqProv: no
Requires: egw-core = %{egwversion}.%{packaging}
%description %{bookmarks}
Manage your bookmarks with eGroupWare. Has Netscape plugin.

%package %{calendar}
Version: %{egwversion}.%{packaging}
Summary: The eGroupWare %{calendar} application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core = %{egwversion}.%{packaging}
%description %{calendar}
Powerful calendar with meeting request system, Alarms, ICal and E-Mail support, 
and ACL security.

%package %{developer_tools}
Version: %{egwversion}.%{packaging}
Summary: The eGroupWare %{developer_tools} application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core = %{egwversion}.%{packaging}
%description %{developer_tools}
The TranslationTools allow to create and extend translations-files for eGroupWare. 
They can search the sources for new / added phrases and show you the ones missing in your language. 

%package egw-pear
Version: %{egwversion}.%{packaging}
Summary: The eGroupWare egw-pear application
Group: Web/Database
#Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core = %{egwversion}.%{packaging}
%description egw-pear
egw-pear contains the pear classes modified to work with eGroupWare

%package %{emailadmin}
Version: %{egwversion}.%{packaging}
Summary: The eGroupWare %{emailadmin} application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core = %{egwversion}.%{packaging}, %{packagename}-egw-pear = %{egwversion}.%{packaging}, php-openssl
%description %{emailadmin}
EmailAdmin allow to maintain User email accounts 

%package %{egwical}
Version: %{egwversion}.%{packaging}
Summary: The eGroupWare %{egwical} application
Group: Web/Database
AutoReqProv: no
Requires: egw-core = %{egwversion}.%{packaging} 
%description %{egwical}
This is the %{egwical} app for eGroupWare.

%package %{felamimail}
Version: %{egwversion}.%{packaging}
Summary: The eGroupWare %{felamimail} application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core = %{egwversion}.%{packaging}, %{packagename}-%{emailadmin} = %{egwversion}.%{packaging}, %{packagename}-egw-pear = %{egwversion}.%{packaging}
%description %{felamimail}
The %{felamimail} Email Reader is a other Email application for eGroupWare.

%package %{filemanager}
Version: %{egwversion}.%{packaging}
Summary: The eGroupWare %{filemanager} application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core = %{egwversion}.%{packaging}, egw-pear = %{egwversion}.%{packaging}
%description %{filemanager}
This is the %{filemanager} app for eGroupWare.

%package %{gallery}
Version: %{egwversion}.%{packaging}
Summary: The eGroupWare %{gallery} application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core = %{egwversion}.%{packaging}, egw-pear = %{egwversion}.%{packaging}
%description %{gallery}
This is the %{gallery} app for eGroupWare.

#%package %{headlines}
#Version: %{egwversion}.%{packaging}
#Summary: The eGroupWare %{headlines} application
#Group: Web/Database
#AutoReqProv: no
#Requires: egw-core = %{egwversion}.%{packaging} 
#%description %{headlines}
#This is the %{headlines} app for eGroupWare.

%package %{icalsrv}
Version: %{egwversion}.%{packaging}
Summary: The eGroupWare %{icalsrv} application
Group: Web/Database
AutoReqProv: no
Requires: egw-core = %{egwversion}.%{packaging} 
%description %{icalsrv}
This is the %{icalsrv} app for eGroupWare.

%package %{infolog}
Version: %{egwversion}.%{packaging}
Summary: The eGroupWare %{infolog} application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core = %{egwversion}.%{packaging}, egw-%{etemplate} = %{egwversion}.%{packaging}
%description %{infolog}
This is the %{infolog} app for eGroupWare (Notes, ToDo, Phonelogs, CRM).

#%package %{jinn}
#Version: %{egwversion}.%{packaging}
#Summary: The eGroupWare %{jinn} application
#Group: Web/Database
#AutoReqProv: no
#Requires: egw-core = %{egwversion}.%{packaging}
#%description %{jinn}
#The %{jinn} app is a multi-site, multi-database, multi-user/-group, database driven Content Management System written in and for the eGroupWare Framework.

%package %{manual}
Version: %{egwversion}.%{packaging}
Summary: The eGroupWare %{manual} application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core = %{egwversion}.%{packaging}
%description %{manual}
This is the %{manual} app for eGroupWare: online help system.

#%package %{messenger}
#Version: %{egwversion}.%{packaging}
#Summary: The eGroupWare %{messenger} application
#Group: Web/Database
#AutoReqProv: no
#Requires: egw-core = %{egwversion}.%{packaging} 
#%description %{messenger}
#This is the %{messenger} app for eGroupWare.

%package %{mydms}
Version: %{egwversion}.%{packaging}
Summary: The eGroupWare %{mydms} application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core = %{egwversion}.%{packaging}, egw-pear = %{egwversion}.%{packaging}
%description %{mydms}
This is a %{mydms} port to eGroupWare.

%package %{news_admin}
Version: %{egwversion}.%{packaging}
Summary: The eGroupWare %{news_admin} application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core = %{egwversion}.%{packaging} 
%description %{news_admin}
This is the %{news_admin} app for eGroupWare.

%package %{phpbrain}
Version: %{egwversion}.%{packaging}
Summary: The eGroupWare %{phpbrain} application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core = %{egwversion}.%{packaging}, %{packagename}-%{addressbook} = %{egwversion}.%{packaging}
%description %{phpbrain}
This is the %{phpbrain} app for eGroupWare.

%package %{phpsysinfo}
Version: %{egwversion}.%{packaging}
Summary: The eGroupWare %{phpsysinfo} application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core = %{egwversion}.%{packaging}
%description %{phpsysinfo}
This is the %{phpsysinfo} app for eGroupWare.

%package %{polls}
Version: %{egwversion}.%{packaging}
Summary: The eGroupWare %{polls} application
Group: Web/Database
AutoReqProv: no
Requires: egw-core = %{egwversion}.%{packaging}
%description %{polls}
This is the %{polls} app for eGroupWare.

%package %{projectmanager}
Version: %{egwversion}.%{packaging}
Summary: The eGroupWare %{projectmanager} application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core = %{egwversion}.%{packaging},
%description %{projectmanager}
The %{projectmanager} is eGroupWare's new project management application.
It's fully integrated into eGroupWare and use the data of InfoLog and Calendar.
Plugable datasources allow to support and manage further applications.

%package %{registration}
Version: %{egwversion}.%{packaging}
Summary: The eGroupWare %{registration} application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core = %{egwversion}.%{packaging}
%description %{registration}
This is the %{registration} app for eGroupWare.

%package %{resources}
Version: %{egwversion}.%{packaging}
Summary: The eGroupWare %{resources} application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core = %{egwversion}.%{packaging}
%description %{resources}
%{resources} is a resource booking sysmtem for eGroupWare.
Which integrates into the calendar.

%package %{sambaadmin}
Version: %{egwversion}.%{packaging}
Summary: The eGroupWare %{sambaadmin} application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core = %{egwversion}.%{packaging}
%description %{sambaadmin}
Manage LDAP based sambaacounts and workstations.

%package %{sitemgr}
Version: %{egwversion}.%{packaging}
Summary: The eGroupWare Sitemanager CMS application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core = %{egwversion}.%{packaging}
%description %{sitemgr}
This is the Sitemanager CMS app for eGroupWare.

%package %{syncml}
Version: %{egwversion}.%{packaging}
Summary: The eGroupWare %{syncml} application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core = %{egwversion}.%{packaging}, php >= 5.0.0
%description %{syncml}
This is the %{syncml} app for eGroupWare.

%package %{timesheet}
Version: %{egwversion}.%{packaging}
Summary: The eGroupWare timesheet application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core = %{egwversion}.%{packaging}
%description %{timesheet}
Simple timesheet application, which allow to record and report
the times and other expenses. It can be uses as well standalone 
as together with the ProjectManager application.

%package %{tracker}
Version: %{egwversion}.%{packaging}
Summary: The eGroupWare trouble ticket system application
Group: Web/Database
AutoReqProv: no
Requires: egw-core = %{egwversion}.%{packaging}
%description %{tracker}
This is the trouble ticket system app for eGroupWare.

%package %{wiki}
Version: %{egwversion}.%{packaging}
Summary: The eGroupWare %{wiki} application
Group: Web/Database
Conflicts: %{packagename}
AutoReqProv: no
Requires: egw-core = %{egwversion}.%{packaging},
%description %{wiki}
This is the %{wiki} app for eGroupWare.

#%package %{workflow}
#Version: %{egwversion}.%{packaging}
#Summary: The eGroupWare %{workflow} application
#Group: Web/Database
#AutoReqProv: no
#Requires: egw-core = %{egwversion}.%{packaging},
#%description %{workflow}
#This is the %{workflow} app for eGroupWare.

%prep
%setup0 -c -n %{egwdirname}
%setup1 -T -D -a 1 -n %{egwdirname}
%setup2 -T -D -a 2 -n %{egwdirname}
%setup3 -T -D -a 3 -n %{egwdirname}
%setup4 -T -D -a 4 -n %{egwdirname}
%setup5 -T -D -a 5 -n %{egwdirname}
#%patch0 -p 0
#%patch1 -p 0

%build

%install
[ "%{buildroot}" != "/" ] && rm -rf %{buildroot}
mkdir -p $RPM_BUILD_ROOT%{prefix}/%{egwdirname}
cp -aRf  etc var $RPM_BUILD_ROOT
cp -aRf egroupware/* $RPM_BUILD_ROOT%{prefix}/%{egwdirname}

rm -f $RPM_BUILD_ROOT%{prefix}/%{egwdirname}/.htaccess
rm -rf $RPM_BUILD_ROOT%{prefix}/%{egwdirname}/debian
rm -rf $RPM_BUILD_ROOT%{prefix}/%{egwdirname}/switchuser
rm -rf $RPM_BUILD_ROOT%{prefix}/%{egwdirname}/skel
rm -rf $RPM_BUILD_ROOT%{prefix}/%{egwdirname}/soap
rm -rf $RPM_BUILD_ROOT%{prefix}/%{egwdirname}/xmlrpc
rm -rf $RPM_BUILD_ROOT%{prefix}/%{egwdirname}/messenger
rm -rf $RPM_BUILD_ROOT%{prefix}/%{egwdirname}/workflow
rm -rf $RPM_BUILD_ROOT%{prefix}/%{egwdirname}/jinn

find $RPM_BUILD_ROOT%{prefix}/%{egwdirname} -name .svn | xargs rm -rf

cd $RPM_BUILD_ROOT%{prefix}/%{egwdirname}
ln -s ../../../var/lib/egroupware/header.inc.php
ln -s sitemgr/sitemgr-link

%clean
[ "%{buildroot}" != "/" ] && rm -rf %{buildroot}

%post
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
%{prefix}/%{egwdirname}/icalsrv.php
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
%{prefix}/%{egwdirname}/phpsysinfo
%{prefix}/%{egwdirname}/polls
%{prefix}/%{egwdirname}/projectmanager
%{prefix}/%{egwdirname}/registration
%{prefix}/%{egwdirname}/resources
%{prefix}/%{egwdirname}/sambaadmin
%{prefix}/%{egwdirname}/sitemgr
%{prefix}/%{egwdirname}/sitemgr-link
%{prefix}/%{egwdirname}/syncml
%{prefix}/%{egwdirname}/timesheet
%{prefix}/%{egwdirname}/tracker
%{prefix}/%{egwdirname}/wiki
%attr(0644,root,root) /etc/cron.d/egroupware
%config %attr(0644,root,root) %{httpdconfd}/egroupware.conf
%if 0%{?suse_version}
	%dir %attr(0755,wwwrun,www) /var/lib/egroupware/default
	%dir %attr(0755,wwwrun,www) /var/lib/egroupware/default/files
	%dir %attr(0755,wwwrun,www) /var/lib/egroupware/default/backup
	%dir %attr(0755,wwwrun,www) /var/lib/egroupware/sessions
	%config %attr(0640,wwwrun,www) /var/lib/egroupware/header.inc.php
%endif
%if 0%{?fedora_version}
	%dir %attr(0755,apache,apache) /var/lib/egroupware/default
	%dir %attr(0755,apache,apache) /var/lib/egroupware/default/files
	%dir %attr(0755,apache,apache) /var/lib/egroupware/default/backup
	%dir %attr(0755,apache,apache) /var/lib/egroupware/sessions
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
%{prefix}/%{egwdirname}/icalsrv.php
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
	%dir %attr(0755,wwwrun,www) /var/lib/egroupware/default
	%dir %attr(0755,wwwrun,www) /var/lib/egroupware/default/files
	%dir %attr(0755,wwwrun,www) /var/lib/egroupware/default/backup
	%dir %attr(0755,wwwrun,www) /var/lib/egroupware/sessions
	%config %attr(0640,wwwrun,www) /var/lib/egroupware/header.inc.php
%endif
%if 0%{?fedora_version}
	%dir %attr(0755,apache,apache) /var/lib/egroupware/default
	%dir %attr(0755,apache,apache) /var/lib/egroupware/default/files
	%dir %attr(0755,apache,apache) /var/lib/egroupware/default/backup
	%dir %attr(0755,apache,apache) /var/lib/egroupware/sessions
	%config %attr(0640,apache,apache) /var/lib/egroupware/header.inc.php
%endif

%files %{addressbook}
%defattr(-,root,root)
%{prefix}/%{egwdirname}/%{addressbook}

%files %{calendar}
%defattr(-,root,root)
%{prefix}/%{egwdirname}/%{calendar}

#%files %{chatty}
#%defattr(-,root,root)
#%{prefix}/%{egwdirname}/%{chatty}

%files %{developer_tools}
%defattr(-,root,root)
%{prefix}/%{egwdirname}/%{developer_tools}

%files egw-pear
%defattr(-,root,root)
%{prefix}/%{egwdirname}/egw-pear

%files %{emailadmin}
%defattr(-,root,root)
%{prefix}/%{egwdirname}/%{emailadmin}

%files %{egwical}
%defattr(-,root,root)
%{prefix}/%{egwdirname}/%{egwical}

%files %{felamimail}
%defattr(-,root,root)
%{prefix}/%{egwdirname}/%{felamimail}

%files %{filemanager}
%defattr(-,root,root)
%{prefix}/%{egwdirname}/%{filemanager}

%files %{gallery}
%defattr(-,root,root)
%{prefix}/%{egwdirname}/%{gallery}

#%files %{headlines}
#%defattr(-,root,root)
#%{prefix}/%{egwdirname}/%{headlines}

%files %{icalsrv}
%defattr(-,root,root)
%{prefix}/%{egwdirname}/%{icalsrv}

%files %{infolog}
%defattr(-,root,root)
%{prefix}/%{egwdirname}/%{infolog}

#%files %{jinn}
#%defattr(-,root,root)
#%{prefix}/%{egwdirname}/%{jinn}

%files %{manual}
%defattr(-,root,root)
%{prefix}/%{egwdirname}/%{manual}

#%files %{messenger}
#%defattr(-,root,root)
#%{prefix}/%{egwdirname}/%{messenger}

%files %{mydms}
%defattr(-,root,root)
%{prefix}/%{egwdirname}/%{mydms}

%files %{news_admin}
%defattr(-,root,root)
%{prefix}/%{egwdirname}/%{news_admin}

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
* Mon Apr 16 2007 Lars Kneschke <l.kneschke@metaways.de> 1.3-019
- eGroupWare 1.4 Beta 4
