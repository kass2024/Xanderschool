#!/usr/bin/env python3
"""Rename Wisdom N2 -> Middle Class and N3 -> Top Class (probe + execute)."""
from __future__ import annotations

import os
import sys
from pathlib import Path

import paramiko

HOST = "66.29.135.120"
USER = "root"
PASSWORD = os.environ.get("VPS_PASSWORD", "6W7sa2g4dMEwcN80ZU")
ROOT = Path(r"C:\xampp7\htdocs\Xander-school")
LOCAL = ROOT / "deploy" / "rename_wisdom_n2_n3_levels.php"
REMOTE = "/opt/xander-school/app/deploy/rename_wisdom_n2_n3_levels.php"


def main() -> int:
    execute = "--execute" in sys.argv
    flag = " --execute" if execute else ""
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(HOST, username=USER, password=PASSWORD, timeout=90, banner_timeout=90)
    sftp = client.open_sftp()
    sftp.put(str(LOCAL), REMOTE)
    print("PUT", LOCAL.name)
    sftp.close()
    cmd = (
        f"docker cp {REMOTE} xander_school_app:/var/www/html/deploy/{LOCAL.name} && "
        f"docker exec xander_school_app php /var/www/html/deploy/{LOCAL.name}{flag}"
    )
    _, stdout, stderr = client.exec_command(cmd, timeout=180)
    out = stdout.read().decode(errors="replace")
    err = stderr.read().decode(errors="replace")
    sys.stdout.buffer.write((out + ("\n" + err if err.strip() else "") + "\n").encode("utf-8", errors="replace"))
    client.close()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
