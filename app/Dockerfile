# Revisionsnummer aus .git des Build-Kontexts (Repo-Root)
FROM alpine:3.19 AS revision
RUN apk add --no-cache git
ARG GIT_REVISION=dev
COPY .git /src/.git
RUN if git --git-dir=/src/.git rev-parse --short HEAD > /REVISION 2>/dev/null; then \
      true; \
    else \
      echo "${GIT_REVISION}" > /REVISION; \
    fi

FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql

COPY app/ /var/www/html/
COPY --from=revision /REVISION /var/www/html/REVISION

EXPOSE 80
