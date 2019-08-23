## CoopCycle installation on Ubuntu 18.04

### Install basic packages
```bash
apt-get update
apt-get install apt-transport-https git make
```

### Add Docker repository and install Docker
```bash
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | apt-key add -
add-apt-repository "deb [arch=amd64] https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable"
apt-get install docker-ce docker-ce-cli containerd.io 
```

### Install Docker-compose
```bash
curl -L "https://github.com/docker/compose/releases/download/1.23.1/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
chmod +x /usr/local/bin/docker-compose
```

### Clone CoopCycle
```bash
git clone https://github.com/trendspotter/coopcycle-web.git /srv/coopcycle
```

### Build CoopCycle
```bash
chown -R $(id -u):82 /srv/coopcycle
chmod -R g+w /srv/coopcycle
cd /srv/coopcycle
docker-compose up
```

### Configure CoopCycle
```bash
make osrm
docker-compose exec php php bin/console doctrine:schema:create --env=dev
docker-compose exec php bin/demo --env=dev
docker-compose exec php php bin/console doctrine:migrations:version --no-interaction --quiet --add --all
mkdir web/images/restaurants web/images/products
chgrp 82 web/images/restaurants web/images/products
chmod g+w web/images/restaurants web/images/products
```

### Create Admin user
```bash
docker-compose exec php bin/console fos:user:create admin
docker-compose exec php bin/console fos:user:promote admin ROLE_ADMIN
```

## Other commands

### Clean Docker logs
```bash
find /var/lib/docker -name '*-json.log' -exec truncate -s 0 {} +
```

## Connect to database
```bash
docker exec -it -u postgres:postgres $(docker container ps | grep postgis | awk '{print $1}') psql coopcycle
```
