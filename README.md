# EGroupware

| Tools | Usage |
| ----- | ----- |
| <img src="https://travis-ci.com/images/logos/TravisCI-Full-Color.png" width="108" alt="Travis CI"/> | runs unit-tests after each commit |
| [![Scrutinizer CI](https://scrutinizer-ci.com/images/logo.png) scrutinizer](https://scrutinizer-ci.com/g/EGroupware/egroupware/) | runs static analysis on our codebase |
| <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn%3AANd9GcQ2scF5HUwLnJVnk2UhYwWpUXHmLQYNXM5yBw&usqp=CAU" width="110" alt="BrowserStack" /> | manual testing with unusual browser versions or platforms |

### Default and prefered installation method for EGroupware is via your Linux package manager:

* [Installation & Update instructions](https://github.com/EGroupware/egroupware/wiki/Installation-using-egroupware-docker-RPM-DEB-package)
* [Distribution specific instructions](https://github.com/EGroupware/egroupware/wiki/Distribution-specific-instructions)

> Every other method (including a developer installation by cloning the repo) is way more complicated AND does not include all features, as part's of EGroupware are running in different containers, eg. the push-server!

### Installing EGroupware 21.1 via Docker for non-Linux environments or not supported Linux distros:
EGroupware 21.1 can be installed via Docker, in fact the DEB/RPM packages also does that. Instructions on how to run EGroupware in Docker are in our [Wiki](https://github.com/EGroupware/egroupware/wiki/Docker-compose-installation) and in [doc/docker](https://github.com/EGroupware/egroupware/tree/21.1/doc/docker) subdirectory.

### Installing EGroupware development version via Docker:
* this is the prefered developer installation, as it contains eg. a push-server container
* https://github.com/EGroupware/egroupware/tree/master/doc/docker/development

### Deprecated EGroupware development installation:
* install composer.phar from https://getcomposer.org/download/
* for JavaScript dependencies and build install nodejs and npm
* optional: for minified CSS install grunt
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
