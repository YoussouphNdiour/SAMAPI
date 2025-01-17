ARG PHP_EXTENSIONS="gd"

FROM thecodingmachine/php:8.3-v4-apache

ENV PHP_EXTENSION_GD=1

ENV TEMPLATE_PHP_INI=production

COPY --chown=docker:docker .env.example /var/www/html/.env

COPY --chown=docker:docker . /var/www/html

ENV APACHE_DOCUMENT_ROOT=public/

RUN composer install

RUN php artisan key:generate
