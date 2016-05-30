# EGroupware
| Branch | Status |
| ------ | ------ |
| master | [![Build Status](https://travis-ci.org/EGroupware/egroupware.svg?branch=master)](https://travis-ci.org/EGroupware/egroupware)
| 14.2 | [![Build Status](https://travis-ci.org/EGroupware/egroupware.svg?branch=14.2)](https://travis-ci.org/EGroupware/egroupware)
### Default and prefered installation method for EGroupware is via your package manager:

  https://software.opensuse.org/download.html?project=server%3AeGroupWare&package=egroupware-epl

### Installing EGroupware 16.1 from github:
* cd /path/to/your/docroot
* git clone https://github.com/EGroupware/egroupware.git # or git@github.com:EGroupware/egroupware.git for ssh
* cd egroupware
* # install composer.phar from https://getcomposer.org/download/
* # install myrepos (mr) from https://myrepos.branchable.com/ or your distribution package manager
* # add a line /path/to/egroupware/.mrconfig to your ~/.mrtrust, to allow running composer.phar and git clone -b 16.1
* mr up
* # enable further / non-default EGroupware apps be uncommenting them in .mrconfig and run mr up
* # continue installation at http://localhost/egroupware/setup/
* # to get minified JavaScript and CSS you need to install nodejs and grunt, if you have not already done so
* # install nodejs from your distribution package manager
* npm install -g grunt-cli # installs grunt globally
* npm install # installes required grunt modules into node_modules/ dir
* grunt # minify javascript and css, needs to run after every update!
