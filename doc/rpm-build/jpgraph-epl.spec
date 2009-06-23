Name: jpgraph-epl
Version: 2.4p
Release:
Summary: Object-Oriented Graph creating library for PHP
Group: Development/Languages
License: JpGraph Professional Bulk License
URL: http://www.aditus.nu/jpgraph/proversion.php
Vendor: Stylite GmbH, http://www.stylite.de/
Packager: Ralf Becker <rb@stylite.de>
Prefix: /usr/share

%if 0%{?suse_version}
	%define php php5
%else
	%define php php
%endif

Source: jpgraph-%{version}.tar.gz
BuildRoot: %{_tmppath}/%{name}-buildroot

Buildarch: noarch

Requires: %{php} >= 5.1.2
Requires: %{php}-gd

%description
JpGraph is a Object-Oriented Graph creating library for PHP.
The library is completely written in PHP and ready to be used in any
PHP scripts (both CGI/APXS/CLI versions of PHP are supported).

The library can be used to create numerous types of graphs either on-line
or written to a file. JpGraph makes it easy to draw both "quick and dirty"
graphs with a minimum of code as well as complex graphs which requires a
very fine grained control. The library assigns context sensitive default
values for most of the parameters which minimizes the learning curve.
The features are there when you need them - not as an obstacle to overcome!

This JpGraph Libary is licensed to Stylite GmbH to be distributed with EGroupaware EPL.

%prep
%setup -c -n jpgraph-%{version}

%build

%install
[ "%{buildroot}" != "/" ] && rm -rf %{buildroot}
mkdir -p $RPM_BUILD_ROOT%{prefix}/jpgraph
rm -rf jpgraph-%{version}/stripped-src
rm -rf jpgraph-%{version}/phpExpress-src
find jpgraph-%{version} -name "*~" -exec rm {} \;
cp -aRf jpgraph-%{version}/* $RPM_BUILD_ROOT%{prefix}/jpgraph

%clean
[ "%{buildroot}" != "/" ] && rm -rf %{buildroot}

%files
%defattr(-,root,root)
%{prefix}/jpgraph

%changelog
* Mon Jun 22 2009 Ralf Becker <rb@stylite.de> 2.4p
  Aditus Professional Version: 2.4p (1 Mar 2009)
* Sat Jun 6 2009 Ralf Becker <rb@stylite.de> 2.3.4
  Aditus Version: 2.3.4 (31 Jan 2009)
