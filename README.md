# EGroupware 20.1
| Branch | Status |
| ------ | ------ |
| 20.1 | [![Build Status](https://travis-ci.org/EGroupware/egroupware.svg?branch=20.1)](https://travis-ci.org/EGroupware/egroupware) |

### Default and prefered installation method for EGroupware is via your package manager:

  https://software.opensuse.org/download.html?project=server%3AeGroupWare%3Atrunk&package=egroupware-docker
  (20.1 is currenlty a release candidate, the repo will change to our stable server:eGroupWare repo after the release!)

### Installing EGroupware 20.1 via Docker:
EGroupware 19.1 can be installed via Docker, in fact the DEB/RPM packages also does that. Instructions on how to run EGroupware in Docker are in [doc/docker](https://github.com/EGroupware/egroupware/tree/20.1/doc/docker) subdirectory.

### Installing EGroupware 20.1 via Composer:
* install composer.phar from https://getcomposer.org/download/
* optional: for minified JavaScript and CSS install nodejs and grunt
```
apt/yum/zypper install nodejs
npm install -g grunt-cli
```
* install EGroupware and dependencies
```
cd /path/to/your/docroot
git clone -b 20.1 https://github.com/EGroupware/egroupware.git # or git@github.com:EGroupware/egroupware.git for ssh
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
- release: taged maintenance releases only eg. 20.1.20200710
- bugfix:  release-branch incl. latest bugfixes eg. 20.1, if you are currently on 20.1.20200710
- \<branch\>: switch to given branch 
- master:  latest development for next release

To change the channel, call ```install-cli.php <channel-to-update-to>```.

For further instalation instructions see our wiki.
