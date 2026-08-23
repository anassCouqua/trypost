FROM php:8.4-apache

WORKDIR /var/www/html

RUN apt-get update && apt-get install -y \
    curl \
    git \
    unzip \
    libpq-dev \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libicu-dev \
    libonig-dev \
    supervisor \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo_pgsql bcmath pcntl gd intl zip opcache \
    && a2enmod rewrite headers \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader --no-scripts

COPY package.json package-lock.json* ./
RUN npm ci

COPY . .

RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache \
    && touch storage/framework/cache/.gitignore storage/framework/sessions/.gitignore storage/framework/views/.gitignore \
    && cp .env.example .env \
    && php artisan key:generate --force \
    && php artisan config:clear \
    && php artisan wayfinder:generate --with-form \
    && npm run build

RUN chown -R www-data:www-data storage bootstrap/cache \
    && sed -i 's/^Listen 80$/Listen 10000/' /etc/apache2/ports.conf \
    && printf '%s\n' \
      '<VirtualHost *:10000>' \
      '    RewriteEngine On' \
      '    RewriteRule ^/$ https://trypost-5g6o.onrender.com/login [R=302,L]' \
      '    DocumentRoot /var/www/html/public' \
      '    <Directory /var/www/html/public>' \
      '        AllowOverride All' \
      '        Require all granted' \
      '    </Directory>' \
      '</VirtualHost>' \
      > /etc/apache2/sites-available/000-default.conf

COPY docker/start.sh /usr/local/bin/start-trypost
RUN chmod +x /usr/local/bin/start-trypost

EXPOSE 10000
CMD ["start-trypost"]
