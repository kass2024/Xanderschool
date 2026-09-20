#!/usr/bin/env python3
"""Deploy student card photo white-background + circle fit helper."""
from __future__ import annotations

import os
import sys
from pathlib import Path

import paramiko

ROOT = Path(r"C:\xampp7\htdocs\Xander-school")
HOST = "66.29.135.120"
USER = "root"
PASSWORD = os.environ.get("VPS_PASSWORD", "6W7sa2g4dMEwcN80ZU")
REMOTE_APP = "/opt/xander-school/app"

FILES = [
    "app/Libraries/ProfilePhotoNormalizer.php",
    "app/Libraries/WisdomCardRenderer.php",
    "app/Controllers/Home.php",
    "app/Helpers/qonics_helper.php",
    "app/Views/pages/students_picture.php",
    "app/Views/templates/student_card_smart.php",
]


def put_file(sftp, rel: str) -> None:
    local = ROOT / rel
    remote = f"{REMOTE_APP}/{rel.replace(chr(92), '/')}"
    sftp.put(str(local), remote)
    print("PUT", rel)


def main() -> int:
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect(HOST, username=USER, password=PASSWORD, timeout=180, banner_timeout=180, auth_timeout=180)
    sftp = c.open_sftp()
    for rel in FILES:
        put_file(sftp, rel)
    sftp.close()
    cmd = r"""
set -e
cd /opt/xander-school/deploy
docker compose -f docker-compose.prod.yml --env-file .env.production restart app
sleep 8
docker exec xander_school_app php -r 'opcache_reset();' || true
docker exec xander_school_app php -l /var/www/html/app/Libraries/ProfilePhotoNormalizer.php
docker exec xander_school_app grep -n "circlePortraitFromImage\|White background\|fit = 'circle'" \
  /var/www/html/app/Libraries/ProfilePhotoNormalizer.php \
  /var/www/html/app/Libraries/WisdomCardRenderer.php \
  /var/www/html/app/Controllers/Home.php \
  /var/www/html/app/Views/pages/students_picture.php | head -25
echo DONE
"""
    _, o, e = c.exec_command(cmd, timeout=180)
    sys.stdout.buffer.write(o.read())
    err = e.read().decode(errors="replace")
    if err.strip() and "Using a password" not in err:
        sys.stderr.write(err)
    code = o.channel.recv_exit_status()
    c.close()
    print("EXIT", code)
    return code


if __name__ == "__main__":
    raise SystemExit(main())
