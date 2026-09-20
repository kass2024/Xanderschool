#!/usr/bin/env python3
"""Fix mPDF autoload on production (Class Mpdf\\Mpdf not found)."""
from __future__ import annotations

import os
import sys
from pathlib import Path

import paramiko

ROOT = Path(r"C:\xampp7\htdocs\Xander-school")
HOST = "66.29.135.120"
USER = "root"
PASSWORD = os.environ.get("VPS_PASSWORD", "6W7sa2g4dMEwcN80ZU")
REMOTE_APP = "/opt/xander-school/app"

FILES = [
    "app/Config/Autoload.php",
    "app/Libraries/MpdfReport.php",
    "vendor/composer/autoload_psr4.php",
    "vendor/composer/autoload_static.php",
    "vendor/composer/installed.json",
    "vendor/composer/installed.php",
]


def put_file(sftp, rel: str) -> None:
    local = ROOT / rel
    remote = f"{REMOTE_APP}/{rel.replace(chr(92), '/')}"
    remote_dir = remote.rsplit("/", 1)[0]
    parts = remote_dir.split("/")
    cur = ""
    for p in parts:
        if p == "":
            continue
        cur += "/" + p
        try:
            sftp.stat(cur)
        except FileNotFoundError:
            sftp.mkdir(cur)
    sftp.put(str(local), remote)
    print("PUT", rel, local.stat().st_size)


def main() -> int:
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect(HOST, username=USER, password=PASSWORD, timeout=180, banner_timeout=180, auth_timeout=180)
    sftp = c.open_sftp()
    for rel in FILES:
        put_file(sftp, rel)
    sftp.close()
    cmd = r"""
set -e
ls -ld /opt/xander-school/app/vendor/mpdf/mpdf /opt/xander-school/app/vendor/mpdf/mpdf/src/Mpdf.php
grep -n "Mpdf\\\\" /opt/xander-school/app/vendor/composer/autoload_psr4.php | head
cd /opt/xander-school/deploy
docker compose -f docker-compose.prod.yml --env-file .env.production restart app
sleep 8
docker exec xander_school_app php -r 'opcache_reset();' || true
docker exec xander_school_app php -r 'require "/var/www/html/vendor/autoload.php"; echo class_exists("Mpdf\\Mpdf") ? "MPDF_OK\n" : "MPDF_MISSING\n"; echo is_file("/var/www/html/vendor/mpdf/mpdf/src/Mpdf.php") ? "FILE_OK\n" : "FILE_MISSING\n";'
echo DONE
"""
    _, o, e = c.exec_command(cmd, timeout=180)
    sys.stdout.buffer.write(o.read())
    err = e.read().decode(errors="replace")
    if err.strip() and "Using a password" not in err:
        sys.stderr.write(err)
    code = o.channel.recv_exit_status()
    c.close()
    print("EXIT", code)
    return code


if __name__ == "__main__":
    raise SystemExit(main())
