#!/usr/bin/env python3
"""Deploy PDF Content-Type fix so the browser opens the staff clock PDF."""
from __future__ import annotations

import os
import sys
from pathlib import Path

import paramiko

ROOT = Path(r"C:\xampp7\htdocs\Xander-school")
HOST = "66.29.135.120"
USER = "root"
PASSWORD = os.environ.get("VPS_PASSWORD", "6W7sa2g4dMEwcN80ZU")
REMOTE = "/opt/xander-school/app/app/Libraries/MpdfReport.php"
LOCAL = ROOT / "app/Libraries/MpdfReport.php"


def main() -> int:
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect(HOST, username=USER, password=PASSWORD, timeout=180, banner_timeout=180, auth_timeout=180)
    sftp = c.open_sftp()
    sftp.put(str(LOCAL), REMOTE)
    sftp.close()
    print("PUT", LOCAL.name)
    cmd = r"""
set -e
cd /opt/xander-school/deploy
docker compose -f docker-compose.prod.yml --env-file .env.production restart app
sleep 8
docker exec xander_school_app php -r 'opcache_reset();' || true
docker exec xander_school_app php -l /var/www/html/app/Libraries/MpdfReport.php
docker exec xander_school_app grep -n "application/pdf\|Destination::STRING_RETURN\|exit;" /var/www/html/app/Libraries/MpdfReport.php
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
