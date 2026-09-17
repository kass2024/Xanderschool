#!/usr/bin/env python3
"""Deploy paged student_v2 sync so large schools (Wisdom) reach the phone quickly."""
from __future__ import annotations

import os
import time
from pathlib import Path

import paramiko

ROOT = Path(r"C:\xampp7\htdocs\Xander-school")
HOST = "66.29.135.120"
USER = "root"
PASSWORD = os.environ.get("VPS_PASSWORD", "6W7sa2g4dMEwcN80ZU")
REMOTE_BASE = "/opt/xander-school/app"

FILES = [
    "app/Controllers/Api.php",
    "app/Models/StudentModel.php",
]

VERIFY_STRINGS = [
    ("app/Controllers/Api.php", "function get_class_roster"),
    ("app/Controllers/Api.php", "has_more"),
    ("app/Models/StudentModel.php", "function getClassRoster"),
    ("app/Models/StudentModel.php", "function getStudentSyncPage"),
]


def main() -> int:
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect(HOST, username=USER, password=PASSWORD, timeout=90, banner_timeout=90)
    sftp = c.open_sftp()
    for rel in FILES:
        remote = f"{REMOTE_BASE}/{rel}".replace("\\", "/")
        print("PUT", rel)
        c.exec_command(f"mkdir -p {remote.rsplit('/', 1)[0]}")
        time.sleep(0.05)
        sftp.put(str(ROOT / rel), remote)
    sftp.close()

    verify_cmds = "\n".join(
        f'grep -q "{needle}" "{REMOTE_BASE}/{path}" || exit 1'
        for path, needle in VERIFY_STRINGS
    )
    cmd = f"""
set -e
docker exec xander_school_app php -l /var/www/html/app/Controllers/Api.php
docker exec xander_school_app php -l /var/www/html/app/Models/StudentModel.php
cd /opt/xander-school/deploy
docker compose -f docker-compose.prod.yml --env-file .env.production restart app
sleep 3
docker exec xander_school_app php -r 'opcache_reset();' || true
{verify_cmds}
echo DONE
"""
    _, o, e = c.exec_command(cmd, timeout=240)
    out = o.read().decode()
    err = e.read().decode()
    print(out)
    if err:
        print(err)
    c.close()
    return 0 if "DONE" in out else 1


if __name__ == "__main__":
    raise SystemExit(main())
