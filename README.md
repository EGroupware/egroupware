# EGroupware

### Default and prefered installation method for EGroupware is via your package manager:

  https://software.opensuse.org/download.html?project=server%3AeGroupWare&package=egroupware-epl

### Installing EGroupware 14.3 from github:
* cd /path/to/your/docroot
* git clone -b 14.2 git@github.com:EGroupware/egroupware.git
* cd egroupware
* # install composer.phar from https://getcomposer.org/download/
* composer.phar install
* # install myrepos (mr) from https://myrepos.branchable.com/ or your distrbution package
* mr up
* # enable further / non-default EGroupware apps be uncommenting them in .mrconfig and run mr up
* continue installation at http://localhost/egroupware/setup/
