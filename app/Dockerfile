FROM php:8.2-apache

# Installiert die PDO-Erweiterung für die MariaDB/MySQL-Verbindung
RUN docker-php-ext-install pdo pdo_mysql

# Kopiert alle PHP-Dateien aus diesem Ordner in den Webserver des Containers
COPY . /var/www/html/

# Git-Revisionsnummer für den Seitenfuß (Build-Argument aus docker-compose / Portainer)
ARG GIT_REVISION=dev
RUN echo "${GIT_REVISION}" > /var/www/html/REVISION

EXPOSE 80