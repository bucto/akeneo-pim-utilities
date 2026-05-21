FROM php:8.2-apache

# Installiert die PDO-Erweiterung für die MariaDB/MySQL-Verbindung
RUN docker-php-ext-install pdo pdo_mysql

# Kopiert alle PHP-Dateien aus diesem Ordner in den Webserver des Containers
COPY . /var/www/html/

EXPOSE 80