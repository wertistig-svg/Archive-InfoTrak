FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock symfony.lock ./
ENV APP_ENV=prod APP_DEBUG=0 APP_SECRET=build-only-secret DATABASE_URL=postgresql://build:build@127.0.0.1:5432/build COMPOSER_ALLOW_SUPERUSER=1
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader --no-scripts

FROM php:8.4-apache
RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev libpq-dev \
    && docker-php-ext-install intl opcache pdo_pgsql \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /var/www/html
COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/infotrak-entrypoint
RUN chmod +x /usr/local/bin/infotrak-entrypoint \
    && mkdir -p var/cache var/log \
    && chown -R www-data:www-data var
ENV APP_ENV=prod APP_DEBUG=0 PORT=10000
EXPOSE 10000
ENTRYPOINT ["infotrak-entrypoint"]
