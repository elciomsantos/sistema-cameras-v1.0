FROM php:8.2-apache

ARG DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y \
        git zip unzip curl libpng-dev libzip-dev libonig-dev \
        libxml2-dev libcurl4-openssl-dev pkg-config libssl-dev \
    && docker-php-ext-install pdo_mysql mbstring zip gd xml curl \
    && pecl install mongodb-stable 2>/dev/null || true \
    && a2enmod rewrite

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

COPY --from=node:20-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
COPY --from=node:20-slim /usr/local/bin/node /usr/local/bin/node
RUN ln -sf /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -sf /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-interaction --optimize-autoloader --no-dev

COPY package.json package-lock.json ./
RUN npm ci && npm run build

COPY . .

RUN mkdir -p storage/ratelimit storage/cep_cache public/uploads \
    && chown -R www-data:www-data storage public/uploads \
    && chmod -R 755 storage public/uploads

RUN echo "memory_limit = 256M" > /usr/local/etc/php/conf.d/memory.ini \
    && echo "upload_max_filesize = 20M" > /usr/local/etc/php/conf.d/upload.ini \
    && echo "post_max_size = 20M" >> /usr/local/etc/php/conf.d/upload.ini \
    && echo "max_execution_time = 120" > /usr/local/etc/php/conf.d/timeout.ini

ENV CAMERAS_ENV=production

RUN a2dissite 000-default.conf && echo '<VirtualHost *:80>\n\
    DocumentRoot /var/www/html/public\n\
    <Directory /var/www/html/public>\n\
        Options -Indexes +FollowSymLinks\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf && a2ensite 000-default.conf

EXPOSE 80

CMD ["apache2-foreground"]
