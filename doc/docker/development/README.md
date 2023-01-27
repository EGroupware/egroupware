# EGroupware development enviroment as Docker container

The container and docker-compose.yml file in this directory are the easiest way to get a full development enviroment for EGroupware.

### It defines and uses the following volumes:
* sources: document root of the webserver, by default $PWD/sources subdirectory, can also be your existing document root
* data: EGroupware stores its files here, by default $PWD/data subdirectory, can also be your existing /var/lib/egroupware
* db: volume for MariaDB (should be NOT a directory under macOS and Windows for performance reasons!)
* sessions: volume for sessions, internal no need to change
* sources-push: swoolpush subdirectory of sources
* collabora-config: /etc/loolwsd for Collabora container, by default $PWD/data/default/loolwsd
* rocketchat-uploads: Upload directory for Rocket.Chat, by default $PWD/data/default/rocketchat/uploads
* rocketchat-dumps: Dump directory for MongoDB, by default $PWD/data/default/rocketchat/dump

### It runs the following containers:
* egroupware: php-fpm
* egroupware-nginx: Nginx
* egroupware-db: MariaDB
* egroupware-push: PHP Swoole based push server
* egroupware-watchtower: to automatic keeps the containers up to date
* phpmyadmin: phpMyAdmin to administrate your MariaDB
* collabora: Collabora Online Office
* rocketchat: Rocket.Chat
* rocketchat-mongo: MongoDB for Rocket.Chat

### Usage:
```
mkdir dev && cd dev
wget https://raw.githubusercontent.com/EGroupware/egroupware/master/doc/docker/development/docker-compose.yml
wget https://raw.githubusercontent.com/EGroupware/egroupware/master/doc/docker/development/nginx.conf
mkdir sources data
# edit docker-compose.yml to fit your needs eg.
# ports to use for Nginx / the webserver, by default 8080 and 4443
# xdebug port, default 9001 (NOT 9000!)
# IDE host, default XDEBUG_REMOTE_HOST=docker.for.mac.localhost
docker-compose up -d egroupware
docker logs -f egroupware
# wait until it says "fpm is running" then press ^C and start the other containers
docker-compose up -d
```
* It will install EGroupware master and phpMyAdmin in egroupware / phpmyadmin subdirectory of sources volume, if not already there
* Credentials for a new install can be found in data:egroupware-docker-install.log
* Use the following to tail the webserver error.log
```
docker logs -f egroupware-nginx 2>&1 | sed "s/PHP message/\\$(echo -e '\n\r')PHP message/g"
```

### Docker Desktop for Mac notes
* directories of volumes must be exported to Docker, by default only your home-directory is!
* permissions of sources and data directory must be readable (sources writable) by your user, as Docker daemon runs as that user!
* db volume must NOT be a directory, as the networked access from Docker VM to the Mac is to slow!

### Docker Desktop for Windows with WSL2 notes
* the directory must be in your Linux home directory ```/home/<username>``` or short ```~/``` (you must NOT use ```/mnt/c/Users/<username>```!)
* until we figure out docker-compose syntax for bind-mounts, you need to replace all mounts using bind-mounts, with explicit mounts, eg:
```
service:
  egroupware:
    volumes:
    #- data:/var/lib/egroupware
    - $PWD/data:/var/lib/egroupware
```
* internal volumes (with just names mentioned in volumes section) are fine

### Docker Desktop for Windows notes
* you can NOT use $PWD to reference the docker-compose directory, use the full path with forward slashes!
* directories of volumes must be exported to Docker!
* db volume must NOT be a directory, as the networked access from Docker VM to Windows is to slow!

### Docker on Linux
* to run docker(-compose) commands with your regular user either
  - prefix them with ```sudo``` or
  - add yourself to the ```docker``` group: ```sudo usermod -aG docker $USER``` and then run ```newgrp docker``` everytime you open a terminal
* permissions of sources directory need to be changed after install: ```chown -R $USER sources```
* permissions of data directory must be readable and writable by www-data user (#33)
* do not use ```http://localhost/egroupware/```, as push, Collabora and Rocket.Chat will not be able to communicate
  - localhost in each container is NOT the host system, but the container itself!
  - give you development system a name and add it to the hosts ```/etc/hosts``` as: ```127.0.0.1   devbox.egroupware.org```
  - add it as ```extra_host: - "devbox.egroupware.org:172.17.0.1"``` to each service which as a commented out extra_host
* Ubuntu 22.04 docker adds firewall rules disabling access to docker0 / 172.17.0.1 from within the containers.
Stopping eg. FPM to access IDE/PHPStorm debug port or a MariaDB running on the host bound to 172.17.0.1. 
To fix that, you have to create an explicit firewall rule to allow containers to acces 172.17.0.1:
```bash
sudo ufw allow from 172.16.0.1/12 to 172.17.0.1/32 port 9001,3306
```