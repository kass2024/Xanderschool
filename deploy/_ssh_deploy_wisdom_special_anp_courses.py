#!/usr/bin/env python3
"""Deploy Wisdom special/ANP course grouping updates."""
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
    "app/Helpers/qonics_helper.php",
    "app/Views/pages/add_course.php",
    "deploy/import_wisdom_remaining_from_workbook.py",
]


def main() -> int:
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(HOST, username=USER, password=PASSWORD, timeout=60, banner_timeout=60)
    sftp = client.open_sftp()
    try:
        for rel in FILES:
            remote = f"{REMOTE_BASE}/{rel}".replace("\\", "/")
            print("PUT", rel)
            sys.stdout.flush()
            sftp.put(str(ROOT / rel), remote)
    finally:
        sftp.close()

    php_files = [
        "/var/www/html/app/Controllers/Home.php",
        "/var/www/html/app/Helpers/qonics_helper.php",
        "/var/www/html/app/Views/pages/add_course.php",
    ]
    for remote_php in php_files:
        cmd = f"docker exec xander_school_app php -l {remote_php}"
        _, stdout, stderr = client.exec_command(cmd, timeout=60)
        out = stdout.read().decode("utf-8", "replace")
        err = stderr.read().decode("utf-8", "replace")
        print(out.encode("ascii", "replace").decode("ascii"))
        if err.strip() and "Deprecated" not in err:
            print(err, file=sys.stderr)
        if stdout.channel.recv_exit_status() != 0:
            client.close()
            return 1

    cmds = [
        "cd /opt/xander-school/deploy && docker compose -f docker-compose.prod.yml --env-file .env.production restart app",
        "docker exec xander_school_app php -r \"if(function_exists('opcache_reset')){opcache_reset(); echo 'opcache_reset OK\\n';}else{echo 'no opcache\\n';}\"",
        "grep -n \"special\\|courseTableSpecial\\|Special (ANP)\" /opt/xander-school/app/app/Controllers/Home.php /opt/xander-school/app/app/Helpers/qonics_helper.php /opt/xander-school/app/app/Views/pages/add_course.php",
    ]
    for cmd in cmds:
        print("RUN", cmd[:100])
        _, stdout, stderr = client.exec_command(cmd, timeout=180)
        out = stdout.read().decode("utf-8", "replace")
        err = stderr.read().decode("utf-8", "replace")
        print(out.encode("ascii", "replace").decode("ascii"))
        code = stdout.channel.recv_exit_status()
        if code != 0:
            print(err, file=sys.stderr)
            client.close()
            return code
        time.sleep(1)

    client.close()
    print("DONE")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
