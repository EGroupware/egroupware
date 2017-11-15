# EGroupware 17.1
| Branch | Status | Composer |
| ------ | ------ | -------- |
| 17.1 | [![Build Status](https://travis-ci.org/EGroupware/egroupware.svg?branch=17.1)](https://travis-ci.org/EGroupware/egroupware) | [![Dependency Status](https://www.versioneye.com/user/projects/57527e0c7757a0003bd4aecb/badge.svg?style=flat)](https://www.versioneye.com/user/projects/57527deb7757a00041b3a25e) |
### Default and prefered installation method for EGroupware is via your package manager:

  https://software.opensuse.org/download.html?project=server%3AeGroupWare&package=egroupware-epl

### Installing EGroupware 17.1 from Github:
* cd /path/to/your/docroot
* git clone -b 17.1 https://github.com/EGroupware/egroupware.git # or git@github.com:EGroupware/egroupware.git for ssh
* cd egroupware
* install composer.phar from https://getcomposer.org/download/
* install myrepos (mr) from https://myrepos.branchable.com/ or your distribution package manager
* add a line /path/to/egroupware/.mrconfig to your ~/.mrtrust, to allow running composer.phar and git clone -b 17.1
* mr up
* enable further / non-default EGroupware apps by uncommenting them in .mrconfig and run mr up
* continue installation at http://localhost/egroupware/setup/
* to get minified JavaScript and CSS you need to install nodejs and grunt, if you have not already done so
* install nodejs from your distribution package manager
* npm install -g grunt-cli # installs grunt command globally, if you have not already done so
* npm install # installs required npm/grunt modules into node_modules/ dir
* run grunt manually after every update, or better uncomment grunt steps in .mrconfig

### Switching a git installation from master or 16.1 to 17.1:
```
for d in . * activesync/vendor/z-push/z-push api/src/Db/ADOdb ; do [ -d $d/.git ] && (echo $d; cd $d; git checkout 17.1); done
```

For further instalation instructions see our wiki.
