#!/usr/bin/env python3
"""Upload Rubavu staff photos and run the white-bg importer on the VPS.

Only WISDOM SCHOOL RUBAVU / school 30 is updated.
"""
from __future__ import annotations

import os
import sys
import time
from pathlib import Path

import paramiko

ROOT = Path(r"C:\xampp7\htdocs\Xander-school")
PHOTO_DIR = Path(
    r"C:\Users\user\Downloads\WISDOM SCHOOL RUBAVU STAFF PICTURES 2026 2027"
    r"\WISDOM SCHOOL RUBAVU STAFF PICTURES 2026 2027"
)
HOST = "66.29.135.120"
USER = "root"
PASSWORD = os.environ.get("VPS_PASSWORD", "6W7sa2g4dMEwcN80ZU")
REMOTE_APP = "/opt/xander-school/app"
REMOTE_PHOTO = f"{REMOTE_APP}/writable/staff_photos_import_rubavu_202627"
PHP_REL = "deploy/import_wisdom_rubavu_staff_photos.php"


def connect():
    last = None
    for i in range(10):
        c = paramiko.SSHClient()
        c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        try:
            print(f"ssh try {i+1}", flush=True)
            c.connect(
                HOST, username=USER, password=PASSWORD,
                timeout=120, banner_timeout=120, auth_timeout=120,
                look_for_keys=False, allow_agent=False,
            )
            print("ssh ok", flush=True)
            return c
        except Exception as e:
            last = e
            print(f"ssh fail {e}", flush=True)
            time.sleep(6)
    raise last


def main() -> int:
    execute = "--execute" in sys.argv
    if not PHOTO_DIR.is_dir():
        print("MISSING photo dir", PHOTO_DIR)
        return 1
    c = connect()
    sftp = c.open_sftp()
    c.exec_command(f"mkdir -p {REMOTE_APP}/deploy {REMOTE_PHOTO}")
    time.sleep(0.3)
    sftp.put(str(ROOT / PHP_REL), f"{REMOTE_APP}/{PHP_REL}")
    print("PUT", PHP_REL, flush=True)
    n = 0
    for path in sorted(PHOTO_DIR.iterdir()):
        if path.suffix.lower() not in {".jpg", ".jpeg", ".png", ".webp"}:
            continue
        remote = f"{REMOTE_PHOTO}/{path.name}"
        sftp.put(str(path), remote)
        n += 1
        print("PUT photo", n, flush=True)
    sftp.close()
    print(f"uploaded_photos={n}", flush=True)

    flag = " --execute" if execute else " --dry-run"
    cmd = (
        "docker cp /opt/xander-school/app/deploy/import_wisdom_rubavu_staff_photos.php "
        "xander_school_app:/var/www/html/deploy/import_wisdom_rubavu_staff_photos.php && "
        "docker exec -e PHOTO_DIR=/var/www/html/writable/staff_photos_import_rubavu_202627 "
        "xander_school_app php /var/www/html/deploy/import_wisdom_rubavu_staff_photos.php"
        + flag
    )
    print("RUN", cmd, flush=True)
    _, stdout, stderr = c.exec_command(cmd, timeout=1200)
    while True:
        line = stdout.readline()
        if not line:
            break
        print(line, end="", flush=True)
    err = stderr.read().decode("utf-8", "replace")
    if err.strip():
        print(err, flush=True)
    code = stdout.channel.recv_exit_status()
    c.close()
    print("EXIT", code, flush=True)
    return code


if __name__ == "__main__":
    raise SystemExit(main())
