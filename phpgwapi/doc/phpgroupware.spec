%define packagename phpGroupWare
%define phpgwdirname phpgroupware
%define version 0.9.9

# This is for Mandrake RPMS 
# (move these below the RedHat ones for Mandrake RPMs)
%define httpdroot  /var/www/html
%define packaging 1mdk

# This is for RedHat RPMS
# (move these below the Mandrake ones for RedHat RPMs)
%define httpdroot  /home/httpd/html
%define packaging 1

Summary: phpGroupWare is a web-based groupware suite written in php. 
Name: %{packagename}
Version: %{version}
Release: %{packaging}
Copyright: GPL
Group: Web/Database
URL: http://www.phpgroupware.org/
Source0: ftp://ftp.sourceforge.net/pub/sourceforge/phpgroupware/%{packagename}-%{version}.tar.bz2
BuildRoot: %{_tmppath}/%{packagename}-buildroot
Prefix: %{httpdroot}
Buildarch: noarch
AutoReq: 0

%description
phpGroupWare is a web-based groupware suite written in PHP. It provides
calendar, todo-list, addressbook, email and a notepad. It also 
provides an API for developing additional applications. See the phpgroupware
apps project for add-on apps.

%prep
%setup -n %{phpgwdirname}

%build
# no build required

%install
rm -rf $RPM_BUILD_ROOT
mkdir -p $RPM_BUILD_ROOT%{prefix}/%{phpgwdirname}
cp -aRf * $RPM_BUILD_ROOT%{prefix}/%{phpgwdirname}
chown -R root:root $RPM_BUILD_ROOT%{prefix}/%{phpgwdirname}
chown -R nobody:nobody $RPM_BUILD_ROOT%{prefix}/%{phpgwdirname}/files/groups
chown -R nobody:nobody $RPM_BUILD_ROOT%{prefix}/%{phpgwdirname}/files/users

%clean
rm -rf $RPM_BUILD_ROOT

%post

%postun

%files
%{prefix}/%{phpgwdirname}

%changelog
* Sat Jan 6 2001 Dan Kuykendall <seek3r@phpgroupware.org> 0.9.9
- Upgraded to new 0.9.8 version.
- Removed lots of unneeded code that was needed for the pre-beta versions.
- Added support for RedHat and Mandrake distro's.
- General clean up so that this can be reused by the project

* Sat Sep 16 2000 Geoffrey Lee <snailtalk@mandrakesoft.com> 09072000-2mdk
- Add url.
- turn off autorequires.
- use /var/www.

* Wed Sep 13 2000  <snailtalk@mandrakesoft.com> 09072000-1mdk
- first rpm-zed distribution.
- cutom configuration files from Dan Kuykendall.
- suggestions on packaging from Dan.

# end of file
