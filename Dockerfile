FROM composer:2.8 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader --no-scripts

FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm ci
COPY resources ./resources
COPY vite.config.* ./
COPY postcss.config.* ./
COPY tailwind.config.* ./
COPY public ./public
RUN npm run build

FROM php:8.4-apache
WORKDIR /var/www/html

RUN apt-get update && apt-get install -y \
    libpq-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libicu-dev unzip supervisor \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo_pgsql gd intl zip opcache \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=assets /app/public/build ./public/build

RUN chown -R www-data:www-data storage bootstrap/cache \
    && printf '%s\n' \
      '<VirtualHost *:80>' \
      '    DocumentRoot /var/www/html/public' \
      '    <Directory /var/www/html/public>' \
      '        AllowOverride All' \
      '        Require all granted' \
      '    </Directory>' \
      '</VirtualHost>' \
      > /etc/apache2/sites-available/000-default.conf

COPY docker/start.sh /usr/local/bin/start-trypost
RUN chmod +x /usr/local/bin/start-trypost

EXPOSE 80
CMD ["start-trypost"]
