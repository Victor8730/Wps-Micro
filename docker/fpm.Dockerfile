FROM php:8.3-fpm

ARG PHP_INI=local.ini

# Install system dependencies.
RUN apt-get update \
  && apt-get install -y libonig-dev libpq-dev libxml2-dev \
    build-essential \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    locales \
    zip \
    jpegoptim optipng pngquant gifsicle \
    vim \
    unzip \
    git \
    curl

# Clear package manager cache.
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions required by the framework.
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
  && docker-php-ext-install -j$(nproc) pdo_mysql mbstring exif pcntl gd dom simplexml opcache

# Copy the selected PHP runtime configuration.
COPY docker/${PHP_INI} /usr/local/etc/php/conf.d/wps-micro.ini
