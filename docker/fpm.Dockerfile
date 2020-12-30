FROM php:7.4-fpm

# dependencies
RUN apt-get update \
  && apt-get install -y libonig-dev libpq-dev \
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

# cache clear
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl
RUN docker-php-ext-install gd

#copy php config
ADD docker/local.ini /usr/local/etc/php/conf.d/local.ini
