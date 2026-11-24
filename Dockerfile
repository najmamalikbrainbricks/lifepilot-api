FROM php:8.3-fpm-alpine

# Install system dependencies & PHP extensions
RUN apk add --no-cache nginx mysql-client git unzip curl bash \
    libpng-dev libzip-dev oniguruma-dev icu-dev ca-certificates \
    && docker-php-ext-install pdo_mysql mbstring gd zip intl opcache bcmath

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Install PHP production dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Set permissions for Nginx and PHP-FPM
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Copy Nginx configuration
COPY render-nginx.conf /etc/nginx/nginx.conf

# Expose web port
EXPOSE 80

# Run migrations and start Nginx + PHP-FPM
CMD php artisan migrate --force && php-fpm -D && nginx -g 'daemon off;'
