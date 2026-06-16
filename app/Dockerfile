# Revisionsnummer: .git, Build-Args oder Quellcode-Hash (Git-Hash zur Laufzeit via GITHUB_TOKEN)
FROM alpine:3.19 AS revision
RUN apk add --no-cache git
ARG GIT_REVISION=dev
ARG GITHUB_SHA=
ARG CI_COMMIT_SHORT_SHA=
WORKDIR /build
COPY . .
RUN REV=""; \
    if [ -d .git ]; then REV="$(git rev-parse --short HEAD 2>/dev/null || true)"; fi; \
    if [ -z "$REV" ] && [ -n "$GITHUB_SHA" ]; then REV="$(printf '%.7s' "$GITHUB_SHA")"; fi; \
    if [ -z "$REV" ] && [ -n "$CI_COMMIT_SHORT_SHA" ]; then REV="$CI_COMMIT_SHORT_SHA"; fi; \
    if [ -z "$REV" ] && [ "$GIT_REVISION" != "dev" ]; then REV="$GIT_REVISION"; fi; \
    if [ -z "$REV" ]; then \
      REV="src$(find app -type f 2>/dev/null | LC_ALL=C sort | xargs sha256sum 2>/dev/null | sha256sum | cut -c1-7)"; \
    fi; \
    if [ -z "$REV" ]; then REV="dev"; fi; \
    echo "$REV" > /REVISION

FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql

COPY app/ /var/www/html/
COPY --from=revision /REVISION /var/www/html/REVISION
COPY app/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh /var/www/html/write_revision.php \
    && chown www-data:www-data /var/www/html/REVISION \
    && chmod 664 /var/www/html/REVISION

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]

EXPOSE 80
