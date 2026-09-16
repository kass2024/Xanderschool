#!/usr/bin/env python3
"""Deploy create-new-post on Change Staff Post modal."""
from __future__ import annotations

import os
import sys
import time
from pathlib import Path

import paramiko

ROOT = Path(r"C:\xampp7\htdocs\Xander-school")
HOST = "66.29.135.120"
USER = "root"
PASSWORD = os.environ.get("VPS_PASSWORD", "6W7sa2g4dMEwcN80ZU")

FILES = [
    "app/Models/PostsModel.php",
    "app/Controllers/Home.php",
    "app/Language/en/app.php",
    "app/Language/fr/app.php",
    "app/Views/main.php",
    "app/Views/main2.php",
    "app/Views/pages/partials/mdl_staff.php",
    "app/Views/pages/staffs.php",
    "public/assets/js/scripts_v1.1.1.js",
]


def main() -> int:
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(HOST, username=USER, password=PASSWORD, timeout=180, banner_timeout=180, auth_timeout=180)
    sftp = client.open_sftp()
    for rel in FILES:
        local = ROOT / rel
        remote = f"/opt/xander-school/app/{rel}".replace("\\", "/")
        parent = remote.rsplit("/", 1)[0]
        client.exec_command(f"mkdir -p {parent}")
        time.sleep(0.05)
        sftp.put(str(local), remote)
        print("PUT", rel)
    sftp.close()

    cmd = r"""
set -e
docker exec xander_school_app php -l /var/www/html/app/Models/PostsModel.php
docker exec xander_school_app php -l /var/www/html/app/Controllers/Home.php
docker exec xander_school_app grep -n "create_post\|createByTitle\|btn-create-post\|change_post_privilege\|Create new post" \
  /var/www/html/app/Controllers/Home.php \
  /var/www/html/app/Models/PostsModel.php \
  /var/www/html/app/Views/main.php \
  /var/www/html/app/Views/pages/staffs.php \
  /var/www/html/public/assets/js/scripts_v1.1.1.js | head -n 40
cd /opt/xander-school/deploy && docker compose -f docker-compose.prod.yml --env-file .env.production restart app
sleep 5
docker exec xander_school_app php -r "opcache_reset(); echo 'OPCACHE_RESET_OK\n';"
"""
    _, stdout, stderr = client.exec_command(cmd, timeout=180)
    sys.stdout.write(stdout.read().decode("utf-8", "replace"))
    err = stderr.read().decode("utf-8", "replace")
    if err.strip():
        sys.stderr.write(err)
    code = stdout.channel.recv_exit_status()
    client.close()
    print("EXIT", code)
    return int(code or 0)


if __name__ == "__main__":
    raise SystemExit(main())
