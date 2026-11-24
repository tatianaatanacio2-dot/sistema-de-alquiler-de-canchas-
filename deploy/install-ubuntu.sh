#!/usr/bin/env bash
set -euo pipefail
[[ $EUID == 0 ]] || { echo 'Ejecutar con sudo en Ubuntu 24.04.' >&2; exit 1; }
apt-get update
apt-get install -y nginx mariadb-server php8.3-fpm php8.3-cli php8.3-mysql php8.3-sqlite3 php8.3-curl php8.3-mbstring php8.3-xml php8.3-zip php8.3-intl php8.3-bcmath composer certbot python3-certbot-nginx rsync unzip curl
id canchas >/dev/null 2>&1 || useradd --create-home --shell /bin/bash canchas
usermod -a -G www-data canchas
install -d -o canchas -g www-data -m 2750 /srv/canchas /srv/canchas/releases /srv/canchas/shared
install -d -o canchas -g www-data -m 2770 /srv/canchas/shared/storage
systemctl enable --now nginx mariadb php8.3-fpm
echo 'Instala Node.js 24 LTS desde su distribución oficial. Configura la BD, .env, SSL y Nginx según deploy/OPERACION.md.'
