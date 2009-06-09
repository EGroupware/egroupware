Name: zend-php-epl
Version: 5.2.9
Release:
Summary: PHP from Zend Server CE for EPL
Group: Development/Languages
License: Zend Server CE is a free product provided by Zend, it is NOT released under an open source license.
URL: http://www.zend.com/en/community/zend-server-ce
Vendor: Stylite GmbH, http://www.stylite.de/
Packager: Ralf Becker <rb@stylite.de>
Prefix: /usr
%define zend_dir /usr/local/zend

%if 0%{?suse_version}
	%define php php5
%else
	%define php php
%endif

#Source: zend.repo
BuildRoot: %{_tmppath}/%{name}-buildroot

Buildarch: noarch

#Requires: %{name}-repo
Requires: zend-base-pe php-dev-zend-pe extension-manager-zend-pe
# hard requirements from egw
Requires: php-mysql-zend-pe php-imap-zend-pe php-gd-zend-pe php-pdo-mysql-zend-pe php-mbstring-zend-pe php-mcrypt-zend-pe
# useful other extensions (sometimes) used by egw
Requires: php-ldap-zend-pe php-bz2-zend-pe php-ctype-zend-pe php-curl-zend-pe php-mime-magic-zend-pe php-posix-zend-pe
#Requires: php5-common-extensions-zend-pe #has to many not listed dependencies
Requires: optimizer-plus-zend-pe
Requires: mod-php5-apache2-zend-pe
# php-mcrypt-zend-pe misses the following dependency:
Requires: libmcrypt

# these are requirements of egroupware(-epl), but not listed as provides in zend server
Provides: php-cli php-common php-xml php-pear php-mbstring php-mysql php-gd php-imap

%description
Zend Server Community Edition (CE) is a free Web Application Server that is
simple to install and easy to use.
It is an ideal solution for anyone running non-critical PHP applications
in production or just experimenting with PHP.

%prep
#%setup -c -T

%build

%install
[ "%{buildroot}" != "/" ] && rm -rf %{buildroot}
mkdir -p $RPM_BUILD_ROOT%{prefix}/bin
cd $RPM_BUILD_ROOT%{prefix}/bin
ln -s %{zend_dir}/bin/php
ln -s %{zend_dir}/bin/pear
ln -s %{zend_dir}/bin/pecl
ln -s %{zend_dir}/bin/phpize
mkdir -p $RPM_BUILD_ROOT/etc
cd $RPM_BUILD_ROOT/etc
ln -s %{zend_dir}/etc/php.ini
ln -s %{zend_dir}/etc/conf.d php.d
mkdir -p $RPM_BUILD_ROOT/etc/profile.d
cd $RPM_BUILD_ROOT/etc/profile.d
ln -s ../zce.rc zend.sh
# Zend Server 4.0.3 has a fixed coded mysql socket: /tmp/mysql.sock
mkdir -p $RPM_BUILD_ROOT/tmp
cd $RPM_BUILD_ROOT/tmp
ln -s /var/lib/mysql/mysql.sock

%clean
[ "%{buildroot}" != "/" ] && rm -rf %{buildroot}

# Zend Server 4.0.3 (mod-php5-apache2-zend-pe) seems not to be able to work with SELinux
# ToDo: add appropriate rules, instead of switching SELinux completly off
%post
%if 0%{?rhel_version} || 0%{?fedora_version} || 0%{?centos_version}
	setenforce 0
%endif

%files
%defattr(-,root,root)
%{prefix}/bin/php
%{prefix}/bin/pear
%{prefix}/bin/pecl
%{prefix}/bin/phpize
/etc/php.ini
/etc/php.d
/etc/profile.d/zend.sh
/tmp/mysql.sock

%changelog
* Sat Jun 6 2009 Ralf Becker <rb@stylite.de> 5.2.9
- using Zend Server CE 4.0.3
