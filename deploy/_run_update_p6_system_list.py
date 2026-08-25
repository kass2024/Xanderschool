#!/usr/bin/env python3
"""Upload P6 parsed Excel JSON + updater and run on production."""
from __future__ import annotations

import os
import sys
from pathlib import Path

import paramiko

ROOT = Path(r"C:\xampp7\htdocs\Xander-school")
HOST = "66.29.135.120"
USER = "root"
PASSWORD = os.environ.get("VPS_PASSWORD", "6W7sa2g4dMEwcN80ZU")
FILES = [
    "deploy/_p6_system_list.json",
    "deploy/update_wisdom_p6_from_system_list.php",
]
EXECUTE = "--execute" in sys.argv


def main() -> int:
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect(HOST, username=USER, password=PASSWORD, timeout=90, banner_timeout=90)
    sftp = c.open_sftp()
    for rel in FILES:
        sftp.put(str(ROOT / rel.replace("/", "\\")), f"/opt/xander-school/app/{rel}")
        print("PUT", rel)
    sftp.close()
    mode = " --execute" if EXECUTE else ""
    cmd = f"""
set -e
docker exec xander_school_app php -l /var/www/html/deploy/update_wisdom_p6_from_system_list.php
docker exec xander_school_app php /var/www/html/deploy/update_wisdom_p6_from_system_list.php{mode}
"""
    _, o, e = c.exec_command(cmd, timeout=180)
    sys.stdout.write(o.read().decode("utf-8", "replace"))
    err = e.read().decode("utf-8", "replace")
    if err.strip():
        sys.stderr.write(err)
    c.close()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())