# Revisionsnummer: .git im Build-Kontext (falls vorhanden) oder Build-Args
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
    if [ -z "$REV" ]; then REV="dev"; fi; \
    echo "$REV" > /REVISION

FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql

COPY app/ /var/www/html/
COPY --from=revision /REVISION /var/www/html/REVISION

EXPOSE 80
