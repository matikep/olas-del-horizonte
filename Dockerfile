FROM php:8.2-apache

RUN docker-php-ext-install pdo_mysql \
 && a2enmod headers \
 && sed -ri 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

COPY docker/php.ini /usr/local/etc/php/conf.d/comite.ini
COPY docker/apache.conf /etc/apache2/conf-enabled/comite.conf
COPY --chown=www-data:www-data . /var/www/html/

# uploads/ va en un volumen persistente (documentos de socios)
RUN mkdir -p /var/www/html/uploads && chown www-data:www-data /var/www/html/uploads
