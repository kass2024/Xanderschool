#!/usr/bin/env python3
"""Deploy P4 A course assignments seed to VPS."""
from __future__ import annotations

import sys
import time
from pathlib import Path

import paramiko

ROOT = Path(r"C:\xampp7\htdocs\Xander-school")
HOST = "66.29.135.120"
USER = "root"
PASSWORD = "6W7sa2g4dMEwcN80ZU"
REMOTE = "/opt/xander-school/app/deploy/seed_wisdom_p4a_course_assignments.php"
LOCAL = ROOT / "deploy/seed_wisdom_p4a_course_assignments.php"


def main() -> int:
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(HOST, username=USER, password=PASSWORD, timeout=60)

    sftp = client.open_sftp()
    client.exec_command("mkdir -p /opt/xander-school/app/deploy")
    time.sleep(0.3)
    sftp.put(str(LOCAL), REMOTE)
    sftp.close()
    print("Uploaded seed script")

    cmd = r"""
docker exec xander_school_app php /var/www/html/deploy/seed_wisdom_p4a_course_assignments.php
cd /opt/xander-school/deploy
docker compose -f docker-compose.prod.yml --env-file .env.production restart app
sleep 3
docker exec xander_school_app php -r 'opcache_reset();' || true
echo DONE
"""
    _, stdout, stderr = client.exec_command(cmd, timeout=180)
    code = stdout.channel.recv_exit_status()
    print(stdout.read().decode(errors="replace"))
    err = stderr.read().decode(errors="replace")
    if err:
        print(err, file=sys.stderr)
    client.close()
    print("EXIT", code)
    return code


if __name__ == "__main__":
    raise SystemExit(main())
