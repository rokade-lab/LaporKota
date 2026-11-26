FROM composer:2.8.12@sha256:5248900ab8b5f7f880c2d62180e40960cd87f60149ec9a1abfd62ac72a02577c AS composer
FROM php:8.4.14-cli-bookworm@sha256:8ee2296ac8fafec3f0a414430c4a6ae90fe9b9165b2eda1146e4e554d7b33daa
RUN apt-get update && apt-get install -y --no-install-recommends libonig-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libargon2-dev unzip git && docker-php-ext-configure gd --with-freetype --with-jpeg && docker-php-ext-install pdo_mysql mbstring gd && rm -rf /var/lib/apt/lists/*
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
RUN php -r 'foreach (["pdo_mysql","mbstring","fileinfo","gd"] as $e) {if (!extension_loaded($e)) exit(1);} if (!in_array("argon2id", password_algos(), true)) exit(1);'
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-progress
COPY . .
