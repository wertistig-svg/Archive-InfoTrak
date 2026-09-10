#!/bin/sh
set -u
cd /var/www/html
while true; do
    php bin/console app:news:import --limit=15 --env=prod --no-debug || echo 'Collecte indisponible; nouvel essai au prochain passage.' >&2
    php bin/console app:news:enrich --limit=60 --env=prod --no-debug || echo 'Résumés indisponibles; nouvel essai au prochain passage.' >&2
    php bin/console app:push:dispatch --env=prod --no-debug || echo 'Notifications indisponibles; nouvel essai au prochain passage.' >&2
    sleep 900
done
