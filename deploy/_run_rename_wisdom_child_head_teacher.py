#!/usr/bin/env python3
"""Rename Wisdom child-school Head masters to Head Teacher and deploy access helpers."""
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
    "app/Config/MenuClearance.php",
    "app/Helpers/qonics_helper.php",
    "app/Controllers/Home.php",
    "app/Controllers/Api.php",
    "app/Api.php",
    "app/Views/pages/staff.php",
    "app/Views/main2.php",
    "app/Services/SchoolHierarchyService.php",
    "deploy/rename_wisdom_child_head_teacher.php",
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
docker exec xander_school_app php -l /var/www/html/app/Config/MenuClearance.php
docker exec xander_school_app php /var/www/html/deploy/rename_wisdom_child_head_teacher.php
echo '--- verify ---'
docker exec xander_school_mysql mysql -uxander_school -pXsApp_3hT6yU1bC5nM9 iotxa_db -t -e "
SELECT sc.id,sc.name,st.id staff_id,CONCAT(st.fname,' ',st.lname) name,st.email,p.title
FROM staffs st
JOIN schools sc ON sc.id=st.school_id
JOIN posts p ON p.id=st.post
WHERE sc.master_school_id=27 AND st.post IN (1,25)
ORDER BY sc.id,st.id;
SELECT COUNT(*) leftover_head_masters FROM staffs st JOIN schools sc ON sc.id=st.school_id WHERE sc.master_school_id=27 AND st.post=1;
"
echo DONE
"""
    _, o, e = c.exec_command(cmd, timeout=240)
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
