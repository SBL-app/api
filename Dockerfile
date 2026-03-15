# syntax=docker/dockerfile:1
FROM php:8.3-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    postgresql-dev \
    postgresql-client \
    icu-dev \
    libzip-dev \
    gmp-dev \
    zip \
    unzip \
    git \
    && docker-php-ext-install \
    pdo_pgsql \
    intl \
    zip \
    opcache \
    gmp

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files first for better caching
COPY composer.json composer.lock ./

# Install dependencies
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Copy application code
COPY . .

# Generate autoloader and run scripts
RUN composer dump-autoload --optimize \
    && composer run-script post-install-cmd --no-interaction || true

# Create var directory and set permissions
RUN mkdir -p var/cache var/log \
    && chown -R www-data:www-data var

# PHP-FPM configuration - listen on all interfaces for Docker networking
RUN sed -i 's/listen = 127.0.0.1:9000/listen = 0.0.0.0:9000/' /usr/local/etc/php-fpm.d/www.conf

# PHP configuration for production
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.memory_consumption=256" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.max_accelerated_files=20000" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/opcache.ini

EXPOSE 9000

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
COPY docker-scheduler-entrypoint.sh /usr/local/bin/docker-scheduler-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh /usr/local/bin/docker-scheduler-entrypoint.sh

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
