# Running EGroupware in Docker

> This is NOT the recommended way of installing EGroupware on a Linux server! 
> Please consult the [installation instructions in our wiki](https://github.com/EGroupware/egroupware/wiki/Installation-using-egroupware-docker-RPM-DEB-package).

## Quick instructions
```
curl https://raw.githubusercontent.com/EGroupware/egroupware/master/doc/docker/docker-compose.yml > docker-compose.yml
curl https://raw.githubusercontent.com/EGroupware/egroupware/master/doc/docker/nginx.conf > nginx.conf
# edit docker-compose.yml or nginx.conf, by default it will run on http://localhost:8080/
# create a few directories upfront, otherwise the containers won't start up:
mkdir data # this is where egroupware data is stored, it's by default a subdir of the directory of docker-compose.yml
mkdir -p data/default/loolwsd # this is where collabora config is stored
mkdir -p data/default/rocketchat/dump # rocket.chat dumps
mkdir -p data/default/rocketchat/uploads # rocket.chat uploads
mkdir sources # egroupware sources will show up in this folder
docker-compose up -d
# grand access rights to source sub folders
#remove sources/egroupware/swolepush --> egw install complains that /egroupware has to be empty
```
## More information
The provided docker-compose.yml will run the following container:
* **egroupware** running latest PHP 8.1 as FPM (see fpm subdirectory for more information)
* **egroupware-push** running PHP 8.1 Swoole Alpine image for websocket connections
* **egroupware-nginx** running Nginx as webserver (by default http only on port 8080)
* **egroupware-db** latest MariaDB 10.6
* **egroupware-watchtower** updating all above container automatically daily at 4am
* **collabora-key** Collabora Online Office
* **collabora-init** Collabora init container to generate the configuration once
* **rocketchat** Rocket.Chat server
* **rocketchat-mongodb** MongoDB for Rocket.Chat
* **portainer** Portainer Docker GUI

## Docker files: docker-compose.yml and docker-compose.overwrite.yml
The [docker-compose.yml](docker-compose.yml) should be left unchanged for easier updating.
You can place your changes in a ```docker-compose.override.yml``` file:
```yml
version: '3'
services:
  egroupware:
    image: egroupware/egroupware:23.1
    extra_hosts:
      - "egw.example.org:172.17.0.1"
```