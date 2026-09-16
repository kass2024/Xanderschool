#!/usr/bin/env python3
"""Add DHT DISCIPLINE and DHT ACADEMICS posts."""
from __future__ import annotations

import os
import sys
from pathlib import Path

import paramiko

ROOT = Path(r"C:\xampp7\htdocs\Xander-school")
HOST = "66.29.135.120"
USER = "root"
PASSWORD = os.environ.get("VPS_PASSWORD", "6W7sa2g4dMEwcN80ZU")

SQL = r"""
INSERT INTO posts (title, status)
SELECT 'DHT DISCIPLINE', 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM posts WHERE title = 'DHT DISCIPLINE');

INSERT INTO posts (title, status)
SELECT 'DHT ACADEMICS', 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM posts WHERE title = 'DHT ACADEMICS');

UPDATE posts SET status = 1 WHERE title IN ('DHT DISCIPLINE', 'DHT ACADEMICS');

SELECT id, title, status FROM posts
WHERE title IN ('DHT DISCIPLINE', 'DHT ACADEMICS', 'Deputy Head Teacher', 'Customer Care')
ORDER BY id;
"""


def main() -> int:
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(HOST, username=USER, password=PASSWORD, timeout=180, banner_timeout=180, auth_timeout=180)
    sftp = client.open_sftp()
    sftp.put(str(ROOT / "app/Models/PostsModel.php"), "/opt/xander-school/app/app/Models/PostsModel.php")
    print("PUT app/Models/PostsModel.php")
    sftp.close()

    stdin, stdout, stderr = client.exec_command(
        "docker exec -i xander_school_mysql mysql -uxander_school -pXsApp_3hT6yU1bC5nM9 iotxa_db -t",
        timeout=90,
    )
    stdin.write(SQL)
    stdin.channel.shutdown_write()
    sys.stdout.write(stdout.read().decode("utf-8", "replace"))
    err = stderr.read().decode("utf-8", "replace")
    if err.strip() and "Using a password" not in err:
        sys.stderr.write(err)

    cmd = r"""
set -e
docker exec xander_school_app grep -n "DHT DISCIPLINE\|DHT ACADEMICS" /var/www/html/app/Models/PostsModel.php
cd /opt/xander-school/deploy && docker compose -f docker-compose.prod.yml --env-file .env.production restart app
sleep 4
docker exec xander_school_app php -r "opcache_reset(); echo 'OPCACHE_RESET_OK\n';"
"""
    _, o, e = client.exec_command(cmd, timeout=180)
    sys.stdout.write(o.read().decode("utf-8", "replace"))
    err2 = e.read().decode("utf-8", "replace")
    if err2.strip():
        sys.stderr.write(err2)
    client.close()
    print("Deploy complete.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
