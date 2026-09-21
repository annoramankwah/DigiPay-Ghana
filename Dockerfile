FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql \
    && a2enmod rewrite \
    && a2dismod mpm_event || true \
    && a2enmod mpm_prefork

WORKDIR /var/www/html

COPY . /var/www/html

# Serve public/ as the document root (matches the .htaccess-based routing
# already used under XAMPP), and allow that .htaccess to actually apply.
RUN sed -i 's#DocumentRoot /var/www/html#DocumentRoot /var/www/html/public#' /etc/apache2/sites-available/000-default.conf \
    && printf '<Directory /var/www/html/public>\n    AllowOverride All\n    Require all granted\n</Directory>\n' >> /etc/apache2/apache2.conf \
    && mkdir -p storage/logs storage/ratelimit storage/backups \
    && chown -R www-data:www-data storage

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
