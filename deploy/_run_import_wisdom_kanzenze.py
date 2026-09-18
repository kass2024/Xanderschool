#!/usr/bin/env python3
"""Upload and run the Wisdom School Kanzenze importer on the VPS.

Only WIS-KAN / school 39 is touched. Excel staff photos are copied into
writable/staff_photos_import_kanzenze first.
"""
from __future__ import annotations

import os
import sys
import time
from pathlib import Path

import paramiko

ROOT = Path(r"C:\xampp7\htdocs\Xander-school")
PHOTO_DIR = ROOT / "deploy" / "_wisdom_kanzenze_staff_photos"
HOST = "66.29.135.120"
USER = "root"
PASSWORD = os.environ.get("VPS_PASSWORD", "6W7sa2g4dMEwcN80ZU")
REMOTE_APP = "/opt/xander-school/app"
REMOTE_PHOTO = f"{REMOTE_APP}/writable/staff_photos_import_kanzenze"

FILES = [
    "deploy/import_wisdom_kanzenze_school_information.php",
    "deploy/_wisdom_kanzenze_school_information.json",
    "app/Libraries/ProfilePhotoNormalizer.php",
    "app/Helpers/qonics_helper.php",
]


def main() -> int:
    dry = "--execute" not in sys.argv
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(HOST, username=USER, password=PASSWORD, timeout=90, banner_timeout=90)
    sftp = client.open_sftp()
    client.exec_command(f"mkdir -p {REMOTE_APP}/deploy {REMOTE_PHOTO}")
    time.sleep(0.2)
    for rel in FILES:
        remote = f"{REMOTE_APP}/{rel}".replace("\\", "/")
        print("PUT", rel)
        sftp.put(str(ROOT / rel), remote)
    if PHOTO_DIR.is_dir():
        for path in sorted(PHOTO_DIR.iterdir()):
            if path.suffix.lower() not in {".jpg", ".jpeg", ".png", ".webp"}:
                continue
            print("PUT photo", path.name)
            sftp.put(str(path), f"{REMOTE_PHOTO}/{path.name}")
    sftp.close()

    flag = " --dry-run" if dry else ""
    cmd = (
        "docker exec -e PHOTO_DIR=/var/www/html/writable/staff_photos_import_kanzenze "
        "xander_school_app php /var/www/html/deploy/import_wisdom_kanzenze_school_information.php"
        + flag
    )
    print("RUN", cmd)
    _, stdout, stderr = client.exec_command(cmd, timeout=1200)
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
