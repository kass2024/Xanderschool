#!/usr/bin/env python3
"""Deploy improved student list Excel export."""
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
REMOTE_BASE = "/opt/xander-school/app"

FILES = [
    "app/Controllers/Home.php",
    "app/Libraries/StudentListExcelExporter.php",
    "app/Views/pages/students.php",
]


def main() -> int:
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect(HOST, username=USER, password=PASSWORD, timeout=60)
    sftp = c.open_sftp()
    for rel in FILES:
        local = ROOT / rel
        remote = f"{REMOTE_BASE}/{rel}".replace("\\", "/")
        print("PUT", rel)
        sys.stdout.flush()
        c.exec_command(f"mkdir -p {remote.rsplit('/', 1)[0]}")
        time.sleep(0.05)
        sftp.put(str(local), remote)
    sftp.close()

    cmd = r"""
set -e
docker cp /opt/xander-school/app/app/Controllers/Home.php xander_school_app:/var/www/html/app/Controllers/Home.php
docker cp /opt/xander-school/app/app/Libraries/StudentListExcelExporter.php xander_school_app:/var/www/html/app/Libraries/StudentListExcelExporter.php
docker exec xander_school_app php -l /var/www/html/app/Controllers/Home.php
docker exec xander_school_app php -l /var/www/html/app/Libraries/StudentListExcelExporter.php
docker exec xander_school_app grep -n "buildMany\|each on its own sheet\|export_smart_student_list" \
  /var/www/html/app/Controllers/Home.php \
  /var/www/html/app/Libraries/StudentListExcelExporter.php | head -n 20
cd /opt/xander-school/deploy
docker compose -f docker-compose.prod.yml --env-file .env.production restart app
sleep 3
docker exec xander_school_app php -r 'opcache_reset(); echo "opcache ok";'
echo DONE
"""
    _, o, e = c.exec_command(cmd, timeout=180)
    sys.stdout.buffer.write(o.read())
    sys.stderr.buffer.write(e.read())
    code = o.channel.recv_exit_status()
    c.close()
    print("EXIT", code)
    return code


if __name__ == "__main__":
    raise SystemExit(main())
