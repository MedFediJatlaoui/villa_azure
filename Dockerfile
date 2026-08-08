FROM wordpress:php8.2-apache

RUN docker-php-ext-install pdo_mysql

COPY apache-cache-headers.conf /etc/apache2/conf-available/cache-headers.conf
RUN a2enmod expires headers && a2enconf cache-headers

COPY googlee0b854359dafb751.html /var/www/html/googlee0b854359dafb751.html
