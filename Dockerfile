# syntax=docker/dockerfile:1

# ---- Étape 1 : dépendances Composer + autoloader optimisé ----
FROM composer:2 AS vendor

WORKDIR /app
COPY . .
# --no-scripts : on ne boote pas Symfony pendant l'installation.
# --ignore-platform-reqs : l'image composer n'a pas toutes les extensions PHP
#   cibles ; elles seront présentes dans l'image runtime.
RUN composer install \
      --no-dev \
      --no-scripts \
      --no-interaction \
      --prefer-dist \
      --optimize-autoloader \
      --ignore-platform-reqs

# ---- Étape 2 : image PHP-FPM de production ----
FROM php:8.3-fpm-alpine AS runtime

# Extensions PHP nécessaires (PostgreSQL + OPcache).
RUN apk add --no-cache --virtual .build-deps postgresql-dev \
    && docker-php-ext-install -j"$(nproc)" pdo pdo_pgsql opcache \
    && apk del .build-deps \
    && apk add --no-cache libpq

# Configuration OPcache orientée production.
RUN { \
      echo 'opcache.enable=1'; \
      echo 'opcache.enable_cli=0'; \
      echo 'opcache.memory_consumption=128'; \
      echo 'opcache.max_accelerated_files=20000'; \
      echo 'opcache.validate_timestamps=0'; \
    } > /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /var/www/html

# Code applicatif + vendor déjà installé (avec autoloader optimisé).
COPY --from=vendor /app ./
RUN chown -R www-data:www-data /var/www/html

USER www-data

EXPOSE 9000
CMD ["php-fpm"]
