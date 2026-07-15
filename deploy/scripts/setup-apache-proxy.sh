#!/bin/bash
# Optional: proxy a domain to Xander-school Docker on 127.0.0.1:8091
# Does NOT touch existing /var/www DocumentRoots or other Apache sites.
set -euo pipefail

PORT="${XANDER_SCHOOL_HTTP_PORT:-8091}"
DOMAIN="${XANDER_SCHOOL_DOMAIN:-}"
CONF="/etc/apache2/sites-available/xander-school.conf"

if [ -z "$DOMAIN" ]; then
  echo "Usage: XANDER_SCHOOL_DOMAIN=school.example.com sudo -E bash $0"
  exit 1
fi

# Prefer localhost binding when using Apache proxy.
# If Docker was published on 0.0.0.0:8091 it still works via 127.0.0.1.

echo "==> Existing /var/www (left unchanged):"
ls -la /var/www 2>/dev/null || true

sudo tee "$CONF" > /dev/null <<EOF
# Xander-school — reverse proxy only (Docker on 127.0.0.1:${PORT})
# Does not use /var/www — existing Apache projects stay as DocumentRoot sites.
<VirtualHost *:80>
    ServerName ${DOMAIN}
    ServerAlias www.${DOMAIN}

    ProxyPreserveHost On
    ProxyPass / http://127.0.0.1:${PORT}/
    ProxyPassReverse / http://127.0.0.1:${PORT}/

    ErrorLog \${APACHE_LOG_DIR}/xander-school-error.log
    CustomLog \${APACHE_LOG_DIR}/xander-school-access.log combined
</VirtualHost>
EOF

sudo a2enmod proxy proxy_http headers rewrite
sudo a2ensite xander-school.conf
sudo apache2ctl configtest
sudo systemctl reload apache2

echo "OK: only new site xander-school.conf added."
echo "Docker stays on port ${PORT}; /var/www untouched."
echo "HTTPS: sudo certbot --apache -d ${DOMAIN} -d www.${DOMAIN}"
