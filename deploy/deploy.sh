#!/usr/bin/env bash
set -euo pipefail
umask 0027
source_dir=$(realpath "${1:?Uso: deploy.sh /ruta/codigo}")
base=/srv/canchas
[[ $(id -un) == canchas ]] || { echo 'Ejecutar como usuario canchas.' >&2; exit 1; }
[[ -f "$source_dir/backend/composer.lock" && -f "$source_dir/frontend/package-lock.json" ]] || exit 1
[[ -s "$base/shared/backend.env" ]] || { echo 'Falta shared/backend.env.' >&2; exit 1; }
exec 9>"$base/shared/deploy.lock"
flock -n 9 || { echo 'Ya hay un despliegue en curso.' >&2; exit 1; }
release="$base/releases/$(date -u +%Y%m%dT%H%M%SZ)-$$"
mkdir -p "$release"
rsync -a --exclude=.git --exclude=.local --exclude=node_modules --exclude=vendor --exclude=.quasar --exclude=dist --exclude=src-capacitor --exclude='*.env*' --exclude='*.apk' --exclude='*.aab' --exclude='*.jks' --exclude='*.keystore' --exclude=storage "$source_dir/" "$release/"
ln -s "$base/shared/backend.env" "$release/backend/.env"
ln -s "$base/shared/storage" "$release/backend/storage"
mkdir -p "$base/shared/storage/"{app/private,app/public,framework/cache/data,framework/sessions,framework/views,logs}
chmod -R g+rwX "$base/shared/storage" "$release/backend/bootstrap/cache"
cd "$release/backend"
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
php -r 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); if(!app()->isProduction() || config("app.debug") || !str_starts_with(config("app.url"),"https://") || !config("app.key")){fwrite(STDERR,"Revisa APP_ENV, APP_DEBUG, APP_URL y APP_KEY.\n");exit(1);}'
cd "$release/frontend"
npm ci
npm run typecheck
npm run build
cp -a dist/spa/. "$release/backend/public/"
cd "$release/backend"
if [[ -L "$base/current" ]]; then
    bash "$source_dir/deploy/backup.sh"
fi
# Las migraciones son aditivas. No ejecutar migrate:fresh ni rollback automático.
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
ln -s "$release" "$base/current.next"
mv -Tf "$base/current.next" "$base/current"
echo "Release activada: $release"
echo 'Comprueba /up y una reserva. Conserva la release anterior para revertir el código.'
