#!/usr/bin/env bash
set -euo pipefail
umask 0077
dest=/var/backups/canchas
credentials=/etc/canchas/backup.cnf
[[ -r "$credentials" ]] || { echo 'Falta /etc/canchas/backup.cnf con [client], user y password de solo lectura.' >&2; exit 1; }
mkdir -p "$dest"
exec 9>"$dest/backup.lock"
flock -n 9 || exit 0
stamp=$(date -u +%Y%m%dT%H%M%SZ)
tmp=$(mktemp "$dest/.backup-XXXXXX")
trap 'rm -f -- "$tmp"' EXIT
mariadb-dump --defaults-extra-file="$credentials" --single-transaction --quick --skip-lock-tables --no-tablespaces canchas_alquiler | gzip -9 > "$tmp"
gzip -t "$tmp"
mv -- "$tmp" "$dest/database-$stamp.sql.gz"
tar -czf "$dest/private-$stamp.tar.gz" -C /srv/canchas/shared backend.env storage/app
sha256sum "$dest/database-$stamp.sql.gz" "$dest/private-$stamp.tar.gz" > "$dest/checksums-$stamp.txt"
echo "Backup creado: $stamp"
# La retención y copia externa se configuran tras comprobar una restauración.
