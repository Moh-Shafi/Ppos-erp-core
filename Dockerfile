FROM php:8.4-fpm-alpine

LABEL maintainer="POS-SaaS"

RUN apk add --no-cache \
    mysql-client \
    libpng-dev \
    libzip-dev \
    oniguruma-dev \
    redis \
    supervisor \
    nginx \
    && docker-php-ext-install \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    && pecl install redis && docker-php-ext-enable redis

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY backend/ .

RUN composer install --no-interaction --optimize-autoloader --no-dev \
    && php artisan optimize:clear \
    && chown -R www-data:www-data /var/www/html

COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
