%define packagename eGroupWare-all-apps
%define egwdirname egroupware
%define version 0.9.99.001
%define packaging 0
%define httpdroot  /var/www/html

Summary: eGroupWare is a web-based groupware suite written in php. 
Name: %{packagename}
Version: %{version}
Release: %{packaging}
Copyright: GPL
Group: Web/Database
URL: http://www.egroupware.org/
Source: eGroupWare-%{version}-%{packaging}.tar.bz2
BuildRoot: /tmp/%{packagename}-buildroot
Prefix: %{httpdroot}
Vendor: eGroupWare
Packager: eGroupWare <r.jung@creativix.net>
Buildarch: noarch
AutoReqProv: no
Requires: php >= 4.0.6
%description
eGroupWare is a web-based groupware suite written in PHP. This package provides:

egroupware core app, addressbook, backup, bookmark, calendar, comic, developer_tools, doc, email, felamimail, forum, ftp, headlines, infolog (CRM), manual, news_admin, phpsysinfo, polls, projects (advanced project management), sitemgr (web content manager), stocks, todo

It also provides an API for developing additional applications. See the egroupware
apps project for add-on apps.

%prep
%setup -n %{egwdirname}

%build
# no build required

%install
[ "%{buildroot}" != "/" ] && rm -rf %{buildroot}
mkdir -p $RPM_BUILD_ROOT%{prefix}/%{egwdirname}
cp -aRf * $RPM_BUILD_ROOT%{prefix}/%{egwdirname}

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
    echo "* <Directory /var/www/egroupware>                 *"
    echo "*   <Files ~ "\.inc\.php$">                       *"
    echo "*      Order allow,deny                           *"
    echo "*      Deny from all                              *"
    echo "*    </Files>                                     *"
    echo "*   <Files ~ ".tpl$">                             *"
    echo "*      Order allow,deny                           *"
    echo "*      Deny from all                              *"
    echo "*   </Files>                                      *"
    echo "* </Directory>                                    *"
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
%{prefix}/%{egwdirname}/version.inc.php
%{prefix}/%{egwdirname}/index.php
%{prefix}/%{egwdirname}/soap.php
%{prefix}/%{egwdirname}/xmlrpc.php
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
%{prefix}/%{egwdirname}/headlines
%{prefix}/%{egwdirname}/infolog
%{prefix}/%{egwdirname}/news_admin
%{prefix}/%{egwdirname}/phpbrain
%{prefix}/%{egwdirname}/phpsysinfo
%{prefix}/%{egwdirname}/polls
%{prefix}/%{egwdirname}/projects
%{prefix}/%{egwdirname}/sitemgr
%{prefix}/%{egwdirname}/stocks
%{prefix}/%{egwdirname}/tts
%{prefix}/%{egwdirname}/wiki

%changelog
* Sat Aug 30 2003 Reiner Jung <r.jung@creativix.net> 0.9.99.000
- initial eGroupWare package anouncement.


# end of file
