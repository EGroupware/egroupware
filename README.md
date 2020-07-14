# EGroupware
| Branch | Status | Tools | Usage |
| ------ | ------ | ----- | ----- |
| master | [![Build Status](https://travis-ci.org/EGroupware/egroupware.svg?branch=master)](https://travis-ci.org/EGroupware/egroupware) | <img src="https://travis-ci.com/images/logos/TravisCI-Full-Color.png" width="108" alt="Travis CI"/> | runs unit-tests after each commit |
| 20.1 | [![Build Status](https://travis-ci.org/EGroupware/egroupware.svg?branch=20.1)](https://travis-ci.org/EGroupware/egroupware) | [![Scrutinizer CI](https://scrutinizer-ci.com/images/logo.png)](https://scrutinizer-ci.com/g/EGroupware/egroupware/) scrutinizer | runs static analysis on our codebase |
| 19.1 | [![Build Status](https://travis-ci.org/EGroupware/egroupware.svg?branch=19.1)](https://travis-ci.org/EGroupware/egroupware) | <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn%3AANd9GcQ2scF5HUwLnJVnk2UhYwWpUXHmLQYNXM5yBw&usqp=CAU" width="110" alt="BrowserStack" /> | manual testing with unusual browser versions or platforms |

### Default and prefered installation method for EGroupware is via your package manager:

  https://software.opensuse.org/download.html?project=server%3AeGroupWare&package=egroupware-epl

### Installing EGroupware 20.1 via Docker:
EGroupware 20.1 can be installed via Docker, in fact the DEB/RPM packages also does that. Instructions on how to run EGroupware in Docker are in [doc/docker](https://github.com/EGroupware/egroupware/tree/20.1/doc/docker) subdirectory.

### Installing EGroupware 19.1 via Docker:
EGroupware 19.1 can be installed via Docker, in fact the DEB/RPM packages also does that. Instructions on how to run EGroupware in Docker are in [doc/docker](https://github.com/EGroupware/egroupware/tree/19.1/doc/docker) subdirectory.


### Installing EGroupware development version:
* install composer.phar from https://getcomposer.org/download/
* optional: for minified JavaScript and CSS install nodejs and grunt
```
apt/yum/zypper install nodejs
npm install -g grunt-cli
```
* install EGroupware and dependencies
```
cd /path/to/your/docroot
git clone https://github.com/EGroupware/egroupware.git # or git@github.com:EGroupware/egroupware.git for ssh
cd egroupware
./install-cli.php
```
* install non-default EGroupware apps by cloning them into your egroupware directory eg.
```
cd /path/to/your/egroupware
git clone https://github.com/EGroupware/wiki.git
```
* continue installation at http://localhost/egroupware/setup/

### Keeping EGroupware up to date or switch to release branch:
```
cd /path/to/your/egroupware
./install-cli.php [<change-channel>]
setup/setup-cli.php # will tell you if a schema-update is necessary
```
install-cli.php supports the following "channels":
- release: taged maintenance releases only eg. 19.1.20200701
- bugfix:  release-branch incl. latest bugfixes eg. 20.1, if you are currently on 20.1.20200710
- \<branch\>: switch to given branch 
- master:  latest development for next release

To change the channel, call ```install-cli.php <channel-to-update-to>```.

For further instalation instructions see our wiki.
