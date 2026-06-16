#!/bin/sh
set -e

php /var/www/html/write_revision.php >/dev/null 2>&1 || true

exec "$@"
