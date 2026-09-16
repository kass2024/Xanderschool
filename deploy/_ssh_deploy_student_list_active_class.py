#!/usr/bin/env python3
"""Deploy student-list active class_records filter."""
from __future__ import annotations

import os
import time
from pathlib import Path

import paramiko

ROOT = Path(r"C:\xampp7\htdocs\Xander-school")
HOST = "66.29.135.120"
USER = "root"
PASSWORD = os.environ.get("VPS_PASSWORD", "6W7sa2g4dMEwcN80ZU")

FILES = ["app/Controllers/Home.php"]


def main() -> int:
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(HOST, username=USER, password=PASSWORD, timeout=90, banner_timeout=90)
    sftp = client.open_sftp()
    for rel in FILES:
        local = ROOT / rel
        remote = f"/opt/xander-school/app/{rel}".replace("\\", "/")
        sftp.put(str(local), remote)
        print("PUT", rel)
    sftp.close()
    cmd = r"""
set -e
docker exec xander_school_app grep -n "cr.status = 1 and students.status IN" /var/www/html/app/Controllers/Home.php
cd /opt/xander-school/deploy && docker compose -f docker-compose.prod.yml --env-file .env.production restart app
sleep 4
docker exec xander_school_app php -r "opcache_reset(); echo 'OPCACHE_RESET_OK\n';"
"""
    _, stdout, stderr = client.exec_command(cmd, timeout=180)
    print(stdout.read().decode())
    err = stderr.read().decode()
    if err.strip():
        print(err)
    client.close()
    print("Deploy complete.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
