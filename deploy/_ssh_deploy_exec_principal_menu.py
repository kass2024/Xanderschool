#!/usr/bin/env python3
"""Give the Executive Principal the same sidebar as the Director."""
from __future__ import annotations

import os
import re
import subprocess
import time
from pathlib import Path

import paramiko

ROOT = Path(r"C:\xampp7\htdocs\Xander-school")
HOST = "66.29.135.120"
USER = "root"
_src = (ROOT / "deploy" / "_ssh_deploy_discipline_codes.py").read_text(encoding="utf-8")
_m = re.search(r'PASSWORD = os\.environ\.get\("VPS_PASSWORD", "([^"]*)"\)', _src)
PASSWORD = os.environ.get("VPS_PASSWORD", _m.group(1) if _m else "")
REMOTE_BASE = "/opt/xander-school/app"
COMMIT = "HEAD"
REL = "app/Config/MenuClearance.php"


def main() -> int:
    data = subprocess.check_output(["git", "show", f"{COMMIT}:{REL}"], cwd=ROOT)
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(HOST, username=USER, password=PASSWORD, timeout=90, banner_timeout=90)
    sftp = client.open_sftp()
    try:
        remote = f"{REMOTE_BASE}/{REL}"
        print("PUT", REL)
        with sftp.file(remote, "w") as fh:
            fh.write(data)
    finally:
        sftp.close()

    cmd = r"""
set -e
cd /opt/xander-school/deploy
docker compose -f docker-compose.prod.yml --env-file .env.production restart app
sleep 6
docker exec xander_school_app php -r 'if (function_exists("opcache_reset")) { opcache_reset(); echo "opcache_reset OK\n"; }'
docker exec xander_school_app grep -n "isExecutivePrincipalPost" /var/www/html/app/Config/MenuClearance.php | head -3
docker exec xander_school_app php -l /var/www/html/app/Config/MenuClearance.php
echo DONE
"""
    _, o, e = client.exec_command(cmd, timeout=240)
    print(o.read().decode("utf-8", "replace"))
    err = e.read().decode("utf-8", "replace")
    if err.strip():
        print(err)
    client.close()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
