#!/usr/bin/env python3
"""Deploy smaller Wisdom staff photo inside the artwork circle."""
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
    "app/Libraries/WisdomStaffCardRenderer.php",
    "app/Libraries/ProfilePhotoNormalizer.php",
    "app/Helpers/qonics_helper.php",
    "app/Views/templates/staff_card_smart.php",
    "deploy/import_wisdom_susa_school_information.php",
]


def main() -> int:
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect(HOST, username=USER, password=PASSWORD, timeout=90, banner_timeout=90)
    sftp = c.open_sftp()
    for rel in FILES:
        sftp.put(str(ROOT / rel), f"{REMOTE_APP}/{rel}")
        print("PUT", rel)
    sftp.close()
    cmd = r"""
set -e
cd /opt/xander-school/deploy
docker compose -f docker-compose.prod.yml --env-file .env.production restart app
sleep 8
docker exec xander_school_app php -r 'opcache_reset();' || true
docker exec xander_school_app php -l /var/www/html/app/Libraries/WisdomStaffCardRenderer.php
docker exec xander_school_app grep -n "fitSubjectInCircle\|subjectBox" \
  /var/www/html/app/Libraries/WisdomStaffCardRenderer.php | head -10
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
