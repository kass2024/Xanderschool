#!/bin/bash
set -e

APP_DIR=/var/www/html

# Ensure Composer vendor (PhpSpreadsheet etc.) when mount lacks it
if [ -f "$APP_DIR/composer.json" ] && [ ! -f "$APP_DIR/vendor/autoload.php" ]; then
  echo "[entrypoint] vendor missing — running composer install --no-dev"
  composer install --working-dir="$APP_DIR" --no-dev --optimize-autoloader --no-interaction || true
fi

# Writable upload/temp folders used by Apache (www-data)
UPLOAD_DIRS=(
  "$APP_DIR/writable"
  "$APP_DIR/public/uploads"
  "$APP_DIR/public/assets/images"
  "$APP_DIR/public/assets/images/profile"
  "$APP_DIR/public/assets/images/logo"
  "$APP_DIR/public/assets/images/background"
  "$APP_DIR/public/assets/images/signatures"
  "$APP_DIR/public/assets/documents"
  "$APP_DIR/public/assets/templates"
  "$APP_DIR/public/assets/reports"
)

for d in "${UPLOAD_DIRS[@]}"; do
  mkdir -p "$d" || true
  chown -R www-data:www-data "$d" || true
  find "$d" -type d -exec chmod 775 {} \; || true
  find "$d" -type f -exec chmod 664 {} \; || true
done

# Background curriculum analyse worker (one class at a time; safe if queue empty)
mkdir -p "$APP_DIR/writable/ai_progress" || true
chown -R www-data:www-data "$APP_DIR/writable/ai_progress" || true
(
  while true; do
    php "$APP_DIR/spark" process:ai-analyse-jobs 5 >> "$APP_DIR/writable/ai_progress/worker.log" 2>&1 || true
    sleep 25
  done
) &

exec "$@"
