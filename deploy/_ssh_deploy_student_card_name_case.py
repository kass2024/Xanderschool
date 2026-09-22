#!/usr/bin/env python3
"""Student card names: first name ALL CAPS, last name Title Case."""
from __future__ import annotations

import os
import socket
import time
from pathlib import Path

import paramiko

ROOT = Path(r"C:\xampp7\htdocs\Xander-school")
HOST = "66.29.135.120"
USER = "root"
PASSWORD = os.environ.get("VPS_PASSWORD", "6W7sa2g4dMEwcN80ZU")
REMOTE_BASE = "/opt/xander-school/app"
FILES = [
    "app/Libraries/CardLayout.php",
    "app/Libraries/WisdomCardRenderer.php",
    "app/Views/templates/student_card_smart.php",
    "app/Controllers/Home.php",
]
VERIFY = [
    ("app/Libraries/CardLayout.php", "function formatStudentCardName"),
    ("app/Libraries/CardLayout.php", "first name ALL CAPS"),
    ("app/Libraries/WisdomCardRenderer.php", "formatStudentCardName"),
    ("app/Views/templates/student_card_smart.php", "formatStudentCardName"),
    ("app/Controllers/Home.php", "students.fname,students.lname"),
]


def wait_port(timeout: int = 180) -> bool:
    end = time.time() + timeout
    while time.time() < end:
        try:
            s = socket.create_connection((HOST, 22), timeout=6)
            s.close()
            return True
        except OSError:
            time.sleep(4)
    return False


def main() -> int:
    if not wait_port(180):
        print("SSH port 22 still closed")
        return 1
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect(HOST, username=USER, password=PASSWORD, timeout=180, banner_timeout=180, auth_timeout=180)
    sftp = c.open_sftp()
    for rel in FILES:
        remote = f"{REMOTE_BASE}/{rel}"
        print("PUT", rel)
        sftp.put(str(ROOT / rel), remote)
    sftp.close()
    verify = "\n".join(
        f'grep -q "{needle}" "{REMOTE_BASE}/{path}" || exit 1' for path, needle in VERIFY
    )
    cmd = f"""
set -e
cd /opt/xander-school/deploy
docker compose -f docker-compose.prod.yml --env-file .env.production restart app
sleep 3
docker exec xander_school_app php -r 'opcache_reset();' || true
{verify}
echo DONE
"""
    _, o, e = c.exec_command(cmd, timeout=240)
    out = o.read().decode()
    err = e.read().decode()
    print(out)
    if err:
        print(err)
    c.close()
    return 0 if "DONE" in out else 1


if __name__ == "__main__":
    raise SystemExit(main())
