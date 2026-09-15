#!/usr/bin/env python3
"""Deploy Share access SMS/email fixes and send credentials to staff Method."""
from __future__ import annotations

import os
import time
from pathlib import Path

import paramiko

ROOT = Path(r"C:\xampp7\htdocs\Xander-school")
HOST = "66.29.135.120"
PASSWORD = os.environ.get("VPS_PASSWORD", "6W7sa2g4dMEwcN80ZU")
REMOTE_APP = "/opt/xander-school/app"

FILES = [
    "app/Controllers/BaseController.php",
    "app/Controllers/Home.php",
    "app/Controllers/Api.php",
    "app/Controllers/Admin.php",
    "app/Views/emails/staff_creation.php",
]

NEEDLES = [
    ("app/Controllers/BaseController.php", "function _smsFailReason"),
    ("app/Controllers/BaseController.php", "timeout' => 30"),
    ("app/Controllers/Home.php", "XanderTech SmartSMS login credentials"),
    ("app/Controllers/Home.php", "_smsFailReason($fail)"),
    ("app/Views/emails/staff_creation.php", "lang(\"app.dear\"); ?> <?="),
]


def main() -> int:
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect(HOST, username="root", password=PASSWORD, timeout=90, banner_timeout=90)
    sftp = c.open_sftp()
    for rel in FILES:
        remote = f"{REMOTE_APP}/{rel.replace(chr(92), '/')}"
        sftp.put(str(ROOT / rel), remote)
        print("PUT", rel)
    sftp.put(str(ROOT / "deploy" / "test_share_staff.php"), f"{REMOTE_APP}/test_share_staff.php")
    print("PUT deploy/test_share_staff.php")
    sftp.close()

    checks = "\n".join(
        f'grep -n "{needle}" {REMOTE_APP}/{path} | head -2' for path, needle in NEEDLES
    )
    cmd = f"""
set -e
docker exec xander_school_app php -l /var/www/html/app/Controllers/BaseController.php
docker exec xander_school_app php -l /var/www/html/app/Controllers/Home.php
docker exec xander_school_app php -l /var/www/html/app/Controllers/Api.php
docker exec xander_school_app php -l /var/www/html/app/Controllers/Admin.php
cd /opt/xander-school/deploy
docker compose -f docker-compose.prod.yml --env-file .env.production restart app
sleep 6
docker exec xander_school_app php -r 'opcache_reset();' || true
{checks}
echo DEPLOY_OK
echo '===== SEND TEST TO METHOD ====='
docker exec xander_school_app php /var/www/html/test_share_staff.php ukipi202@gmail.com
echo '===== LAST COMMS ====='
docker exec xander_school_app sh -c 'grep -E "swiftqom: (SUCCESS|FAIL)|EMAIL.*(SUCCESS|FAIL)|ukipi202|250780699435" /var/www/html/writable/logs/comms-$(date +%Y-%m-%d).log | tail -20'
"""
    _, o, e = c.exec_command(cmd, timeout=180)
    print(o.read().decode("utf-8", errors="replace"))
    err = e.read().decode("utf-8", errors="replace")
    if err.strip():
        print("STDERR:", err[:3000])
    c.close()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
