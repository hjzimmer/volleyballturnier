# Dockerfile für Volleyball Turnierplaner
# Basis-Image mit PHP und SQLite
FROM php:8.2-apache

# Installiere SQLite3
#RUN apt-get update && \
#    apt-get install -y sqlite3 libsqlite3-dev && \
#    docker-php-ext-install pdo pdo_sqlite

# Apache-Konfiguration: Setze DocumentRoot auf /var/www/html/php
RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/Turnierplaner/php|g' /etc/apache2/sites-available/000-default.conf
#RUN echo 'Alias /timer /var/www/html/Turnierplaner/php/Timer/countdown.php\n<Directory /var/www/html/Turnierplaner/php/Timer>\n    Require all granted\n</Directory>' > /etc/apache2/conf-available/timer-alias.conf && \
#    a2enconf timer-alias
RUN echo 'Alias /timer /var/www/html/Turnierplaner/php/Timer\n<Directory /var/www/html/Turnierplaner/php/Timer>\n    Require all granted\n</Directory>' > /etc/apache2/conf-available/timer-alias.conf && \
    a2enconf timer-alias    

# Kopiere alles außer php und data
COPY . /var/www/html/Turnierplaner

# Setze Rechte für das data-Verzeichnis (optional, falls Schreibzugriff benötigt)
RUN chown -R www-data:www-data /var/www/html/Turnierplaner/data && \
    chmod -R 755 /var/www/html/Turnierplaner/data

# Exponiere Port 80
EXPOSE 80

# Starte Apache im Vordergrund
CMD ["apache2-foreground"]
