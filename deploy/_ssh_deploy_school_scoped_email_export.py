#!/usr/bin/env python3
"""Deploy school-scoped student email/list export."""
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
    "app/Views/pages/students.php",
]


def main() -> int:
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    last = None
    for i in range(8):
        try:
            c.connect(
                HOST, username=USER, password=PASSWORD,
                timeout=120, banner_timeout=120, auth_timeout=120,
                look_for_keys=False, allow_agent=False,
            )
            last = None
            break
        except Exception as e:
            last = e
            time.sleep(6)
    if last:
        raise last
    sftp = c.open_sftp()
    for rel in FILES:
        local = ROOT / rel
        remote = f"{REMOTE_BASE}/{rel}".replace("\\", "/")
        print("PUT", rel, flush=True)
        sftp.put(str(local), remote)
    sftp.close()

    cmd = r"""
set -e
docker cp /opt/xander-school/app/app/Controllers/Home.php xander_school_app:/var/www/html/app/Controllers/Home.php
docker cp /opt/xander-school/app/app/Views/pages/students.php xander_school_app:/var/www/html/app/Views/pages/students.php
docker exec xander_school_app php -l /var/www/html/app/Controllers/Home.php
docker exec xander_school_app php -l /var/www/html/app/Views/pages/students.php
docker exec xander_school_app grep -n "schoolOwnedAcademicYearId\|students.school_id" /var/www/html/app/Controllers/Home.php | head -n 30
cd /opt/xander-school/deploy
docker compose -f docker-compose.prod.yml --env-file .env.production restart app
sleep 4
docker exec xander_school_app php -r 'opcache_reset(); echo "opcache ok\n";'
echo DONE
"""
    _, o, e = c.exec_command(cmd, timeout=180)
    sys.stdout.buffer.write(o.read())
    sys.stderr.buffer.write(e.read())
    code = o.channel.recv_exit_status()
    c.close()
    print("EXIT", code, flush=True)
    return code


if __name__ == "__main__":
    raise SystemExit(main())
