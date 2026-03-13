# Dockerfile für Volleyball Turnierplaner
# Basis-Image mit PHP und SQLite
FROM php:8.2-apache

# Installiere SQLite3, Python3 und PHP-Erweiterungen
RUN apt-get update && \
    apt-get install -y sqlite3 libsqlite3-dev python3 python3-pip && \
    docker-php-ext-install pdo pdo_sqlite && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

# Apache-Konfiguration: Setze DocumentRoot auf /var/www/html/php
RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/php|g' /etc/apache2/sites-available/000-default.conf
#RUN echo 'Alias /timer /var/www/html/php/Timer/countdown.php\n<Directory /var/www/html/php/Timer>\n    Require all granted\n</Directory>' > /etc/apache2/conf-available/timer-alias.conf && \
#    a2enconf timer-alias
RUN echo 'Alias /timer /var/www/html/php/Timer\n<Directory /var/www/html/php/Timer>\n    Require all granted\n</Directory>' > /etc/apache2/conf-available/timer-alias.conf && \
    a2enconf timer-alias
RUN echo 'Alias /data /var/www/html/data\n<Directory /var/www/html/data>\n    Require all granted\n</Directory>' > /etc/apache2/conf-available/data-alias.conf && \
    a2enconf data-alias

# Kopiere alles außer das data-Verzeichnis (via .dockerignore ausgeschlossen)
COPY . /var/www/html/

# Setze Rechte für das Web-Verzeichnis
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

# Exponiere Port 80
EXPOSE 80

# Starte Apache im Vordergrund
CMD ["apache2-foreground"]
