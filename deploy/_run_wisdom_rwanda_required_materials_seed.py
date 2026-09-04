#!/usr/bin/env python3
"""Upload and run WISDOM Rwanda required-materials seed on VPS."""
from __future__ import annotations

import sys
import time
from pathlib import Path

import paramiko

ROOT = Path(r"C:\xampp7\htdocs\Xander-school")
HOST = "66.29.135.120"
USER = "root"
PASSWORD = "6W7sa2g4dMEwcN80ZU"
REMOTE = "/opt/xander-school/app/deploy/seed_wisdom_rwanda_required_materials.php"
LOCAL = ROOT / "deploy/seed_wisdom_rwanda_required_materials.php"


def main() -> int:
    dry_run = "--dry-run" in sys.argv
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(HOST, username=USER, password=PASSWORD, timeout=90, banner_timeout=90)

    sftp = client.open_sftp()
    client.exec_command("mkdir -p /opt/xander-school/app/deploy")
    time.sleep(0.3)
    sftp.put(str(LOCAL), REMOTE)
    sftp.close()
    print("Uploaded seed script")

    suffix = " --dry-run" if dry_run else ""
    cmd = (
        "docker exec xander_school_app "
        f"php /var/www/html/deploy/seed_wisdom_rwanda_required_materials.php{suffix}"
    )
    _, stdout, stderr = client.exec_command(cmd, timeout=300)
    code = stdout.channel.recv_exit_status()
    out = stdout.read().decode(errors="replace")
    err = stderr.read().decode(errors="replace")
    if out:
        sys.stdout.buffer.write(out.encode("utf-8", "replace"))
        sys.stdout.buffer.write(b"\n")
    if err:
        sys.stderr.buffer.write(err.encode("utf-8", "replace"))
        sys.stderr.buffer.write(b"\n")
    client.close()
    return int(code)


if __name__ == "__main__":
    raise SystemExit(main())
