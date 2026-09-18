#!/usr/bin/env python3
"""Deploy profile-photo crop + white background pipeline."""
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
    "app/Helpers/qonics_helper.php",
    "app/Controllers/Home.php",
    "deploy/import_wisdom_susa_school_information.php",
]


def main() -> int:
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect(HOST, username=USER, password=PASSWORD, timeout=90, banner_timeout=90)
    sftp = c.open_sftp()
    for rel in FILES:
        local = ROOT / rel
        if not local.is_file():
            print("MISSING", rel)
            return 1
        remote = f"{REMOTE_APP}/{rel.replace(chr(92), '/')}"
        print("PUT", rel)
        sftp.put(str(local), remote)
    sftp.close()
    cmd = r"""
set -e
cd /opt/xander-school/deploy
docker compose -f docker-compose.prod.yml --env-file .env.production restart app
sleep 8
docker exec xander_school_app php -r 'opcache_reset();' || true
docker exec xander_school_app php -l /var/www/html/app/Libraries/ProfilePhotoNormalizer.php
docker exec xander_school_app grep -n "save_profile_photo_white_bg\|ProfilePhotoNormalizer\|pure solid white" \
  /var/www/html/app/Helpers/qonics_helper.php \
  /var/www/html/app/Libraries/ProfilePhotoNormalizer.php \
  /var/www/html/app/Controllers/Home.php | head -20
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
