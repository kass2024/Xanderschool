#!/usr/bin/env python3
"""Upload and run the Kayonza login-email update on the VPS.

Only WSY / school 35 is touched. Sets the head-teacher login to
umutonihenry@gmail.com and keeps the default child password.
"""
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
REMOTE_APP = "/opt/xander-school/app"

FILES = [
    "deploy/set_wisdom_kayonza_login.php",
]


def main() -> int:
    dry = "--execute" not in sys.argv
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(HOST, username=USER, password=PASSWORD, timeout=90, banner_timeout=90)
    sftp = client.open_sftp()
    client.exec_command(f"mkdir -p {REMOTE_APP}/deploy")
    time.sleep(0.2)
    for rel in FILES:
        remote = f"{REMOTE_APP}/{rel}".replace("\\", "/")
        print("PUT", rel)
        sftp.put(str(ROOT / rel), remote)
    sftp.close()

    flag = " --dry-run" if dry else ""
    cmd = (
        "docker exec xander_school_app php /var/www/html/deploy/set_wisdom_kayonza_login.php"
        + flag
    )
    print("RUN", cmd)
    _, stdout, stderr = client.exec_command(cmd, timeout=180)
    code = stdout.channel.recv_exit_status()
    out = stdout.read().decode(errors="replace")
    err = stderr.read().decode(errors="replace")
    if out:
        print(out)
    if err:
        print(err, file=sys.stderr)
    client.close()
    print("EXIT", code)
    return code


if __name__ == "__main__":
    raise SystemExit(main())
