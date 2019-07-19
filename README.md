# EGroupware
| Branch | Status | Tools | Usage |
| ------ | ------ | ----- | ----- |
| master | [![Build Status](https://travis-ci.org/EGroupware/egroupware.svg?branch=master)](https://travis-ci.org/EGroupware/egroupware) | <img src="https://discourse-cdn-sjc1.com/business4/uploads/travis_ci/original/1X/e8f4998fa50cfd83f42adedbde4b9cca3c80e168.png" width="108" alt="Travis CI"/> | runs unit-tests after each commit |
| 17.1 | [![Build Status](https://travis-ci.org/EGroupware/egroupware.svg?branch=17.1)](https://travis-ci.org/EGroupware/egroupware) | [![Scrutinizer CI](https://scrutinizer-ci.com/images/logo.png)](https://scrutinizer-ci.com/g/EGroupware/egroupware/) scrutinizer | runs static analysis on our codebase |
| 16.1 | [![Build Status](https://travis-ci.org/EGroupware/egroupware.svg?branch=16.1)](https://travis-ci.org/EGroupware/egroupware) | <img src="https://p14.zdusercontent.com/attachment/1015988/zGiHOaPFC0e9nG4PJWXZH1Ibe?token=eyJhbGciOiJkaXIiLCJlbmMiOiJBMTI4Q0JDLUhTMjU2In0..i_vov1AU7X1qzPu3nYPL-Q.tdgDtQMrCCHEACS4aXPcYTKf8BeQUVGohG0-UFeEnJwC_d4KvmSz4EaV2NpQmQ9B-m-3Sj9EyfxNVW6XSOksFBd-QZz2CLu5il0ko2KdvC4YjEGFQkmPJOAPfVQfwXcZkg08eaX-4aluvvwBQQv5oXRbCVVX-lMtYE_1OPBTYPRZoBEWEnIlCGix05vC82EOvqzQ2Ht9lGpwfFLMfawIT5l-sCi5vAluKBJKR2pkduFqq3DD_E1Y2YqPA3utD3zDfcU5Zb4Qqd1pfkES3_9L3CnluMmIKG6bY5aFDyLPPUE.KENm6nkdraKqs2JzQIwHpQ" width="110" alt="BrowserStack"/> | manual testing with unusual browser versions or platforms |

### Default and prefered installation method for EGroupware is via your package manager:

  https://software.opensuse.org/download.html?project=server%3AeGroupWare&package=egroupware-epl

### Installing EGroupware 19.1+ via Docker:
EGroupware 19.1 can be installed via Docker, in fact the DEB/RPM packages also does that. Instructions on how to run EGroupware in Docker are in [doc/docker](https://github.com/EGroupware/egroupware/tree/master/doc/docker) subdirectory.

### Installing EGroupware 17.1 from github:
[switch to 17.1 branch](https://github.com/EGroupware/egroupware/tree/17.1) and follow instructions there

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
- release: taged maintenance releases only eg. 17.1.20190222
- bugfix:  release-branch incl. latest bugfixes eg. 17.1, if you are currently on 17.1.20190222
- \<branch\>: switch to given branch 
- master:  latest development for next release

To change the channel, call ```install-cli.php <channel-to-update-to>```.

For further instalation instructions see our wiki.
