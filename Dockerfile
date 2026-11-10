FROM php:7.4-cli

RUN apt-get update -y && apt-get install -y libmcrypt-dev libicu-dev g++ libzip-dev zip libcurl3-dev curl libpng-dev libfreetype6-dev \
	           libjpeg-dev libjpeg62-turbo-dev gnupg bzip2 libxtst6 libgtk-3-0 libx11-xcb-dev libdbus-glib-1-2 libxt6 libpci-dev  \
	          && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure intl

RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install -j$(nproc) gd
RUN docker-php-ext-install zip pdo_mysql intl curl

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer
