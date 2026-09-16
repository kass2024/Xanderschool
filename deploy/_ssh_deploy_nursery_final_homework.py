#!/usr/bin/env python3
"""Deploy final nursery timetable: no after-lunch courses, merged HOME WORK."""
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
    "app/Models/TimetableSchemaModel.php",
    "app/Services/Timetable/NurseryTimetableCriteria.php",
    "app/Services/Timetable/TimetableGeneratorService.php",
    "app/Services/Timetable/TimetableStagingService.php",
    "app/Controllers/TimetableManagement.php",
    "app/Views/pages/timetable/_grid_body.php",
    "app/Views/pages/partials/timetable_settings.php",
    "public/assets/css/timetable.css",
    "deploy/regenerate_wisdom_nursery.php",
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
docker exec xander_school_app php -l /var/www/html/app/Models/TimetableSchemaModel.php
docker exec xander_school_app php -l /var/www/html/app/Services/Timetable/NurseryTimetableCriteria.php
docker exec xander_school_app php -l /var/www/html/app/Services/Timetable/TimetableGeneratorService.php
docker exec xander_school_app php -l /var/www/html/deploy/regenerate_wisdom_nursery.php
docker exec xander_school_app grep -n "HOME WORK\|slotIsAfterLunch\|MIN_DISTINCT_COURSES_PER_DAY = 3\|tt-homework-row" \
  /var/www/html/app/Models/TimetableSchemaModel.php \
  /var/www/html/app/Services/Timetable/NurseryTimetableCriteria.php \
  /var/www/html/app/Views/pages/timetable/_grid_body.php \
  /var/www/html/public/assets/css/timetable.css | head -n 40
cd /opt/xander-school/deploy && docker compose -f docker-compose.prod.yml --env-file .env.production restart app
sleep 5
docker exec xander_school_app php -r "opcache_reset(); echo 'OPCACHE_RESET_OK\n';"
echo '--- REGEN NURSERY ---'
docker exec xander_school_app php /var/www/html/deploy/regenerate_wisdom_nursery.php 27
"""
    _, stdout, stderr = client.exec_command(cmd, timeout=300)
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
