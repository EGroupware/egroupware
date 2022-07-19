# Running EGroupware in Docker

> This is NOT the recommended way of installing EGroupware on a Linux server! 
> Please consult the [installation instructions in our wiki](https://github.com/EGroupware/egroupware/wiki/Installation-using-egroupware-docker-RPM-DEB-package).

## Quick instructions
```
curl https://raw.githubusercontent.com/EGroupware/egroupware/master/doc/docker/docker-compose.yml > docker-compose.yml
curl https://raw.githubusercontent.com/EGroupware/egroupware/master/doc/docker/nginx.conf > nginx.conf
# edit docker-compose.yml or nginx.conf, by default it will run on http://localhost:8080/
mkdir data # this is where egroupware data is stored, it's by default a subdir of the directory of docker-compose.yml
docker-compose up -d
```
## More information
The provided docker-compose.yml will run the following container:
* **egroupware** running latest PHP 7.3 as FPM (see fpm subdirectory for more information)
* **egroupware-push** runing latest PHP Swoole for websocket connections
* **egroupware-nginx** running Nginx as webserver (by default http only on port 8080)
* **egroupware-db** latest MariaDB 10.4
* **egroupware-watchtower** updating all above container automatically daily at 4am
* **collabora-key** Collabora Online Office
* **collabora-init** Collabora init container to generate the configuration once
* **rocketchat** Rocket.Chat server
* **rocketchat-mongodb** MongoDB for Rocket.Chat
* **portainer** Portainer Docker GUI
```
version: '3'
volumes:
  sources:
  db:
  data:
    driver_opts:
      type: none
      o: bind
      # to upgrade an existing non-docker installation most easy is to use the existing
      # data directory /var/lib/egroupware AND the host database see below
      #device: /var/lib/egroupware
      # otherwise data is stored in data subdirectory of the current directory
      device: $PWD/data
  # extra sources with apps not part of egroupware container
  #extra:
  #  driver_opts:
  #    type: none
  #    o: bind
  #    # location of deprecated EGroupware packages like Wiki, SiteMgr, KnowledgeBase
  #    device: /usr/share/egroupware
  #    #device: $PWD/extra
  # collabora-config
  # sources for push server, swoolpush subdirectory of egroupware
  sources-push:
    driver_opts:
      type: none
      o: bind
      device: $PWD/sources/egroupware/swoolepush
  # volume to store config.inc.php file / token shared between egroupware and push container
  push-config:
  sessions:
  collabora-config:
    driver_opts:
      type: none
      o: bind
      # to upgrade an existing non-docker installation most easy is to use the existing
      # data directory /var/lib/egroupware AND the host database see below
      #device: /var/lib/egroupware/default/loolwsd
      # otherwise data is stored in data subdirectory of the current directory
      device: $PWD/data/default/loolwsd
  # store Rocket.Chat MongoDB on an (internal) Volume
  mongo:
  # directory to store MongoDB dumps
  rocketchat-dumps:
    driver_opts:
      type: none
      o: bind
      device: $PWD/data/default/rocketchat/dump
  rocketchat-uploads:
    driver_opts:
      type: none
      o: bind
      device: $PWD/data/default/rocketchat/uploads
services:
  egroupware:
    image: egroupware/egroupware:20.1
    # EPL image: download.egroupware.org/egroupware/epl:20.1
    # setting a default language for a new installation
    #environment:
    #- LANG=de
    volumes:
    - sources:/usr/share/egroupware
    # extra-sources rsync from entry-point into sources
    #- extra:/usr/share/egroupware-extra
    - data:/var/lib/egroupware
    - sessions:/var/lib/php/sessions
    - push-config:/var/lib/egroupware-push
    # if you want to use the host database:
    # 1. comment out the whole db service below AND
    # 2. set EGW_DB_HOST=localhost AND
    # 3. uncomment the next line and modify the host path (first one), it depends on your distro:
    #    - RHEL/CentOS   /var/lib/mysql/mysql.sock:/var/run/mysqld/mysqld.sock
    #    - openSUSE/SLE  /var/run/mysql/mysql.sock:/var/run/mysqld/mysqld.sock
    #    - Debian/Ubuntu /var/run/mysqld:/var/run/mysqld
    #- /var/run/mysqld:/var/run/mysqld
    # private CA so egroupware can validate your certificate to talk to Collabora or Rocket.Chat
    # multiple certificates (eg. a chain) have to be single files in a directory, with one named private-ca.crt!
    #- /etc/egroupware-docker/private-ca.crt:/usr/local/share/ca-certificates/private-ca.crt:ro
    environment:
    # MariaDB/MySQL host to use: for internal service use "db", for host database (socket bind-mounted into container) use "localhost"
    - EGW_DB_HOST=db
    # grant host is needed for NOT using localhost / unix domain socket for MySQL/MariaDB
    - EGW_DB_GRANT_HOST=172.%
    # for internal db service you should to specify a root password here AND in db service
    # a database "egroupware" with a random password is created for you on installation (password is stored in header.inc.php in data directory)
    #- EGW_DB_ROOT=root
    - EGW_DB_ROOT_PW=secret
    # alternativly you can specify an already existing database with full right by the given user!
    #- EGW_DB_NAME=egroupware
    #- EGW_DB_USER=egroupware
    #- EGW_DB_PASS=
    # further post_install.php arguments can be passed as a single enviroment variable with space separated assignments
    # "<name1>=<value1> <name2>=<value2>" see https://github.com/EGroupware/egroupware/blob/master/doc/rpm-build/post_install.php#L17
    # to configure eg. LDAP for authentication and account storage use
    #- EGW_POST_INSTALL='account-auth=ldap,ldap ldap_base=ou=egroupware,dc=example,dc=org ldap_host=tls://ldap.example.org ldap_admin=cn=admin,$base ldap_admin_pw=secret ldap_context=cn=users,$base ldap_group_context=cn=groups,$base'
    restart: always
    depends_on:
    - db
    container_name: egroupware
    # set the ip-address of your docker host AND your official DNS name so EGroupware
    # can access Rocket.Chat or Collabora without the need to go over your firewall
    #extra_hosts:
    #- "my.host.name:ip-address"

  # push server using phpswoole
  push:
    image: phpswoole/swoole:latest
    volumes:
      - sources-push:/var/www
      - sessions:/var/lib/php/sessions
      - push-config:/var/lib/egroupware-push
    container_name: egroupware-push
    restart: always
    # as we get our sources from there
    depends_on:
      - egroupware

  nginx:
    image: nginx:stable-alpine
    volumes:
    - sources:/usr/share/egroupware:ro
    # to add a certificate create a certificate.pem containing (in that order)
    # 1. private key
    # 2. public key
    # 3. (optional) chain certificates
    # uncomment to the next line
    # ./certificate.pem:/etc/ssl/private/certificate.pem
    # AND uncomment the three lines starting with "listen 443", "ssl_certificate", "ssl_certificate_key" in nginx.conf
    - ./nginx.conf:/etc/nginx/conf.d/default.conf:ro
    ports:
    # if no webserver is running on the host, change (first) number to 80 or 443
    - "8080:80"
    - "4443:443"
    depends_on:
    - egroupware
    - collabora-key
    - rocketchat
    container_name: egroupware-nginx

  # run an own MariaDB:10.4 (you can use EGroupware's database backup and restore to add your existing database)
  db:
    image: mariadb
    environment:
    #- MYSQL_ROOT=root
    - MYSQL_ROOT_PASSWORD=secret
    volumes:
    - db:/var/lib/mysql
    container_name: egroupware-db

  # automatic updates of all containers daily at 4am
  # see https://containrrr.github.io/watchtower for more information
  watchtower:
    image: containrrr/watchtower
    volumes:
    - /var/run/docker.sock:/var/run/docker.sock
    # For automatic EPL Updates (not necessary for CE!) you need to pass docker
    # credentials into watchtower after running: docker login download.egroupware.org
    #- /root/.docker/config.json:/config.json:ro
    environment:
    - WATCHTOWER_CLEANUP=true # delete old image after update to not fill up the disk
    # for email notifications add your email and mail-server here
    #- WATCHTOWER_NOTIFICATIONS=email
    #- WATCHTOWER_NOTIFICATIONS_LEVEL=info # possible values: panic, fatal, error, warn, info or debug
    #- WATCHTOWER_NOTIFICATION_EMAIL_FROM=watchtower@my-domain.com
    #- WATCHTOWER_NOTIFICATION_EMAIL_TO=me@my-domain.com"
    #- WATCHTOWER_NOTIFICATION_EMAIL_SERVER=mail.my-domain.com # if you give your MX here, you need no user/password
    #- WATCHTOWER_NOTIFICATION_EMAIL_SERVER_PORT=25
    #- WATCHTOWER_NOTIFICATION_EMAIL_SERVER_USER=watchtower@my-domain.com
    #- WATCHTOWER_NOTIFICATION_EMAIL_SERVER_PASSWORD="secret"
    command: --schedule "0 0 4 * * *"
    container_name: egroupware-watchtower
    restart: always

  # Collabora Online Office
  collabora-key:
    image: "quay.io/egroupware/collabora-key:stable"
    #image: collabora/code:latest
    # needs to be initialised via: docker run --rm -v dev_collabora-config:/mnt --entrypoint '/bin/cp -r /etc/loolwsd /mnt' quay.io/egroupware/collabora-key:stable
    volumes:
      - collabora-config:/etc/loolwsd
    # dont try to regenerate the (not used certificate) as volumn is readonly
    environment:
      - DONT_GEN_SSL_CERT=1
    restart: always
    container_name: collabora-key
    # set the ip-address of your docker host AND your official DNS name so Collabora
    # can access EGroupware without the need to go over your firewall
    #extra_hosts:
    #- "my.host.name:ip-address"
    depends_on:
      - collabora-init

  # initialise the collabora-config volume
  collabora-init:
    image: "quay.io/egroupware/collabora-key:latest"
    command:  bash -c "test -f /tmp/coolwsd/coolwsd.xml || (cp -p /etc/coolwsd/* /tmp/coolwsd && cd /tmp/coolwsd && ln -s coolwsd.conf loolwsd.conf)"
    volumes:
      - collabora-config:/tmp/coolwsd

  # Rocket.Chat server
  rocketchat:
    image: rocketchat/rocket.chat:latest
    command: bash -c 'for i in `seq 1 30`; do node main.js && s=$$? && break || s=$$?; echo "Tried $$i times. Waiting 5 secs..."; sleep 5; done; (exit $$s)'
    restart: unless-stopped
    volumes:
      - rocketchat-uploads:/app/uploads
    # if EGroupware uses a certificate from a private CA, OAuth authentication will fail, you need to:
    # - have the CA certificate stored at /etc/egroupware-docker/private-ca.crt
    # - uncomment the next 2 lines about the private CA:
    # - /etc/egroupware-docker/private-ca.crt:/usr/local/share/ca-certificates/private-ca.crt:ro
    environment:
      # - NODE_EXTRA_CA_CERTS=/usr/local/share/ca-certificates/private-ca.crt
      # IMPORTANT: change ROOT_URL to your actual url eg. https://domain.com/rocketchat
      - ROOT_URL=http://localhost/rocketchat
      - PORT=3000
      - MONGO_URL=mongodb://mongo:27017/rocketchat
      - MONGO_OPLOG_URL=mongodb://mongo:27017/local
    #     - HTTP_PROXY=http://proxy.domain.com
    #     - HTTPS_PROXY=http://proxy.domain.com
    depends_on:
      - mongo
    container_name: rocketchat
    # set the ip-address of your docker host AND your official DNS name so Rocket.Chat
    # can access EGroupware without the need to go over your firewall
    #extra_hosts:
    #- "my.host.name:ip-address"

  # MongoDB for Rocket.Chat
  mongo:
    image: mongo:4.0
    restart: unless-stopped
    volumes:
      - mongo:/data/db
      - rocketchat-dumps:/dump
    command: mongod --smallfiles --oplogSize 128 --replSet rs0 --storageEngine=mmapv1
    container_name: rocketchat-mongo
  # this container's job is just run the command to initialize the replica set.
  # it will run the command and remove himself (it will not stay running)
  mongo-init-replica:
    image: mongo:4.0
    command: 'bash -c "for i in `seq 1 30`; do mongo mongo/rocketchat --eval \"rs.initiate({ _id: ''rs0'', members: [ { _id: 0, host: ''localhost:27017'' } ]})\" && s=$$? && break || s=$$?; echo \"Tried $$i times. Waiting 5 secs...\"; sleep 5; done; (exit $$s)"'
    depends_on:
      - mongo

  # Portainer: Docker GUI (needs to be enabled in nginx.conf too!)
#   portainer:
#    image: portainer/portainer
#    command: -H unix:///var/run/docker.sock
#    restart: always
#    ports:
#      - 9000:9000
#      - 8000:8000
#    volumes:
#      - /var/run/docker.sock:/var/run/docker.sock
#      - portainer_data:/data
#    container_name: portainer
```