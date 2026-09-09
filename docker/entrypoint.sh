#!/bin/sh
set -eu
cd /var/www/html
: "${APP_SECRET:?APP_SECRET doit etre renseigne dans Render.}"
: "${DATABASE_URL:?DATABASE_URL doit etre renseigne dans Render.}"
sed -i "s/^Listen .*/Listen ${PORT:-10000}/" /etc/apache2/ports.conf

attempt=0
until php bin/console doctrine:migrations:migrate --env=prod --no-debug --no-interaction; do
    attempt=$((attempt + 1))
    if [ "$attempt" -ge 30 ]; then
        echo "Les migrations n'ont pas pu etre appliquees apres 30 tentatives." >&2
        exit 1
    fi
    sleep 2
done

php bin/console cache:clear --env=prod --no-debug
php bin/console app:seed --env=prod --no-debug
# Startup commands run as root; Apache must be able to write the generated cache.
chown -R www-data:www-data var
exec apache2-foreground
