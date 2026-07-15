#!/bin/bash
set -e

APP_DIR=/var/www/html

if [ -d "$APP_DIR/writable" ]; then
  chown -R www-data:www-data "$APP_DIR/writable" || true
  find "$APP_DIR/writable" -type d -exec chmod 775 {} \; || true
  find "$APP_DIR/writable" -type f -exec chmod 664 {} \; || true
fi

if [ -f "$APP_DIR/public/uploads" ] || [ -d "$APP_DIR/public/uploads" ]; then
  chown -R www-data:www-data "$APP_DIR/public/uploads" || true
fi

exec "$@"
