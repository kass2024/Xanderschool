#!/usr/bin/env python3
"""Deploy complete P4 A student seed + visitor relationship fix."""
from __future__ import annotations

import sys
import time
from pathlib import Path

import paramiko

ROOT = Path(r"C:\xampp7\htdocs\Xander-school")
HOST = "66.29.135.120"
USER = "root"
PASSWORD = "6W7sa2g4dMEwcN80ZU"
REMOTE_BASE = "/opt/xander-school/app"

FILES = [
    "app/Models/StudentVisitorModel.php",
    "deploy/seed_wisdom_p4a_students.php",
]


def main() -> int:
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(HOST, username=USER, password=PASSWORD, timeout=60)
    sftp = client.open_sftp()
    for rel in FILES:
        local = ROOT / rel
        remote = f"{REMOTE_BASE}/{rel}".replace("\\", "/")
        print("PUT", rel)
        client.exec_command(f"mkdir -p {remote.rsplit('/', 1)[0]}")
        time.sleep(0.05)
        sftp.put(str(local), remote)
    sftp.close()

    cmd = r"""
docker exec xander_school_app php /var/www/html/deploy/seed_wisdom_p4a_students.php
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
