#!/bin/sh
set -eu
cd /var/www/html
sed -i "s/^Listen .*/Listen ${PORT:-10000}/" /etc/apache2/ports.conf
php bin/console cache:clear --env=prod --no-debug

attempt=0
until php bin/console doctrine:migrations:migrate --env=prod --no-debug --no-interaction; do
    attempt=$((attempt + 1))
    if [ "$attempt" -ge 30 ]; then
        echo "Les migrations n'ont pas pu etre appliquees apres 60 secondes." >&2
        exit 1
    fi
    sleep 2
done

php bin/console app:seed --env=prod --no-debug
exec apache2-foreground
