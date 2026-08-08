FROM wordpress:php8.2-apache

RUN docker-php-ext-install pdo_mysql
