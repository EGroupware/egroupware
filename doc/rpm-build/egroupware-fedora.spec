Name: eGroupWare
Version: 0.9.99
Release: 0.fdr.1.rc6
Epoch: 0
Summary: eGroupWare is a web-based groupware suite written in php. 

Group: Web/Database
License: GPL/LGPL
URL: http://www.egroupware.org/
Source0: http://download.sourceforge.net/egroupware/eGroupWare-0.9.99.0.fdr.1.rc6.tar.gz
BuildRoot: %{_tmppath}/%{name}-%{version}-%{release}-root-%(%{__id_u} -n)
Requires: php php-mysql php-imap php-xmlrpc



%description
eGroupWare is a web-based groupware suite written in PHP. This package provides:

egroupware core app, addressbook, backup, bookmark, calendar, comic, developer_tools, doc, email, felamimail, forum, ftp, headlines, infolog (CRM), manual, news_admin, phpsysinfo, polls, projects (advanced project management), sitemgr (web content manager), stocks, todo

It also provides an API for developing additional applications. See the egroupware
apps project for add-on apps.

%prep
%setup -n egroupware

%build

%install
[ "%{buildroot}" != "/" ] && rm -rf %{buildroot}
mkdir -p $RPM_BUILD_ROOT/var/www/html/egroupware
cp -aRf * $RPM_BUILD_ROOT/var/www/html/egroupware

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
    echo "* <Directory /var/www/html/egroupware>            *"
    echo "*   <Files ~ "\.\(inc.php\|tpl\)$">               *"
    echo "*      Order allow,deny                           *"
    echo "*      Deny from all                              *"
    echo "*    </Files>                                     *"
    echo "* </Directory>                                    *"
    echo "***************************************************"

%postun

%files
%defattr(0744,root,root)
%dir /var/www/html/egroupware 
/var/www/html/egroupware/*

%changelog
* Thu Jun 29 2004 Reiner Jung <r.jung@creativix.net> 0.9.99.00.fdr.1.rc6
- JiNN extended.
- projects updated
- new knowledge base available
- new language available Catalan
- many languages updated
- bug fixes in all applications
- extend the usage of indexes for DB tables


* Thu Apr 27 2004 Reiner Jung <r.jung@creativix.net> 0.9.99.00.fdr.1.rc5
- rewrite of projects added.
- Wiki with WYSIWYG editor added
- bugfixes for sitemgr
- email don't need longer php-imap module, many bugfixes for email included
- Traditional Chinese lang updated
- Danish lang updated
- Italien lang files updated
- jerryr template updated
- many bugs fixed in all applications

