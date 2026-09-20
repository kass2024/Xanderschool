#!/usr/bin/env python3
"""Deploy staff dashboard recent rows with stable staff ids for kiosk photos."""
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
    "app/Libraries/StaffShiftClock.php",
]

VERIFY_STRINGS = [
    ("app/Libraries/StaffShiftClock.php", "s.id as staff_id"),
    ("app/Libraries/StaffShiftClock.php", "'id' => (int) ($r['staff_id'] ?? $r['user_id'] ?? 0)"),
]


def main() -> int:
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(HOST, username=USER, password=PASSWORD, timeout=90, banner_timeout=90)
    sftp = client.open_sftp()
    for rel in FILES:
        remote = f"{REMOTE_BASE}/{rel}".replace("\\", "/")
        print("PUT", rel)
        client.exec_command(f"mkdir -p {remote.rsplit('/', 1)[0]}")
        time.sleep(0.05)
        sftp.put(str(ROOT / rel), remote)
    sftp.close()

    verify_cmds = "\n".join(
        f'grep -Fq "{needle}" "{REMOTE_BASE}/{path}" || exit 1'
        for path, needle in VERIFY_STRINGS
    )
    cmd = f"""
set -e
docker exec xander_school_app php -l /var/www/html/app/Libraries/StaffShiftClock.php
cd /opt/xander-school/deploy
docker compose -f docker-compose.prod.yml --env-file .env.production restart app
sleep 3
docker exec xander_school_app php -r 'opcache_reset();' || true
{verify_cmds}
echo DONE
"""
    _, stdout, stderr = client.exec_command(cmd, timeout=240)
    out = stdout.read().decode()
    err = stderr.read().decode()
    print(out)
    if err:
        print(err)
    client.close()
    return 0 if "DONE" in out else 1


if __name__ == "__main__":
    raise SystemExit(main())
