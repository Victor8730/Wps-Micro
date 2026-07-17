# syntax=docker/dockerfile:1.7

FROM node:24-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json vite.config.js ./
RUN npm ci

COPY resources ./resources
COPY application/Views ./application/Views
RUN npm run build

FROM php:8.3-fpm AS php-base

ARG PHP_INI=production.ini

RUN docker-php-ext-install -j$(nproc) pdo_mysql

COPY docker/${PHP_INI} /usr/local/etc/php/conf.d/wps-micro.ini

WORKDIR /var/www/wps-micro

FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
  --no-dev \
  --prefer-dist \
  --no-interaction \
  --no-progress \
  --optimize-autoloader

FROM php-base AS fpm

COPY . .
COPY --from=vendor /app/application/vendor ./application/vendor
COPY --from=frontend /app/public/build ./public/build

RUN mkdir -p application/Cache \
  && chown -R www-data:www-data application/Cache

EXPOSE 9000

CMD ["php-fpm"]

FROM nginx:alpine AS nginx

COPY docker/conf/vhost.production.conf /etc/nginx/conf.d/default.conf
COPY --from=fpm /var/www/wps-micro/public /var/www/wps-micro/public

WORKDIR /var/www/wps-micro
