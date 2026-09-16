#!/usr/bin/env python3
"""Upload and run Wisdom primary exact-Excel lock/create on production."""
from __future__ import annotations

import os
import sys
from pathlib import Path

import paramiko

ROOT = Path(r"C:\xampp7\htdocs\Xander-school")
HOST = "66.29.135.120"
USER = "root"
PASSWORD = os.environ.get("VPS_PASSWORD", "6W7sa2g4dMEwcN80ZU")
EXECUTE = "--execute" in sys.argv

FILES = [
    "deploy/_remap_student_class.php",
    "deploy/apply_wisdom_primary_exact.php",
    "deploy/_primary_exact_plan.json",
]


def main() -> int:
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(HOST, username=USER, password=PASSWORD, timeout=90, banner_timeout=90)
    sftp = client.open_sftp()
    for rel in FILES:
        local = ROOT / rel
        remote = f"/opt/xander-school/app/{rel}".replace("\\", "/")
        sftp.put(str(local), remote)
        print("PUT", rel, local.stat().st_size)
    sftp.close()
    flag = " --execute" if EXECUTE else ""
    cmd = f"docker exec xander_school_app php /var/www/html/deploy/apply_wisdom_primary_exact.php{flag}"
    print("RUN", cmd)
    _, stdout, stderr = client.exec_command(cmd, timeout=180)
    sys.stdout.write(stdout.read().decode("utf-8", "replace"))
    err = stderr.read().decode("utf-8", "replace")
    if err.strip() and "Using a password" not in err:
        sys.stderr.write(err)
    client.close()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
