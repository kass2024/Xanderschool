#!/usr/bin/env python3
"""Upload and run a Wisdom deploy PHP script on production."""
from __future__ import annotations

import os
import sys
from pathlib import Path

import paramiko

ROOT = Path(r"C:\xampp7\htdocs\Xander-school")
HOST = "66.29.135.120"
USER = "root"
PASSWORD = os.environ.get("VPS_PASSWORD", "6W7sa2g4dMEwcN80ZU")


def main() -> int:
    if len(sys.argv) < 2:
        print("Usage: _run_wisdom_php.py <script.php> [--execute]")
        return 2
    name = sys.argv[1]
    extra = " ".join(sys.argv[2:])
    local = ROOT / "deploy" / name
    remote = f"/opt/xander-school/app/deploy/{name}"
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(HOST, username=USER, password=PASSWORD, timeout=90, banner_timeout=90)
    sftp = client.open_sftp()
    sftp.put(str(local), remote)
    print("PUT", name)
    sftp.close()
    flag = f" {extra}" if extra.strip() else ""
    cmd = (
        f"docker cp {remote} xander_school_app:/var/www/html/deploy/{name} && "
        f"docker exec xander_school_app php /var/www/html/deploy/{name}{flag}"
    )
    _, stdout, stderr = client.exec_command(cmd, timeout=600)
    out = stdout.read().decode(errors="replace")
    err = stderr.read().decode(errors="replace")
    sys.stdout.buffer.write((out + ("\n" + err if err.strip() else "") + "\n").encode("utf-8", errors="replace"))
    client.close()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
