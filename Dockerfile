FROM php:8.2-apache

# Install system dependencies and PHP extensions required by the app
RUN apt-get update && apt-get install -y --no-install-recommends \
        ca-certificates \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libzip-dev \
        default-mysql-client \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" mysqli pdo_mysql gd zip \
    && a2enmod rewrite headers expires \
    && rm -rf /var/lib/apt/lists/*

# Enable .htaccess overrides in /var/www/html
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Use production php.ini defaults
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

WORKDIR /var/www/html
COPY . /var/www/html

# Ensure writable runtime directories exist
RUN mkdir -p storage/logs storage/uploads storage/merchandise storage/rate_limits tmp backups \
    && chown -R www-data:www-data storage tmp backups

EXPOSE 80

# Render injects $PORT at runtime; bind Apache to it first and run idempotent DB migration in background
CMD ["sh", "-c", "PORT=${PORT:-80}; sed -i \"s/Listen 80/Listen ${PORT}/g\" /etc/apache2/ports.conf && sed -i \"s/:80/:${PORT}/g\" /etc/apache2/sites-available/000-default.conf || exit 1; (php /var/www/html/tools/migrate_fk_and_indexes.php --safe-boot >/proc/1/fd/1 2>&1 || true) & exec apache2-foreground"]
