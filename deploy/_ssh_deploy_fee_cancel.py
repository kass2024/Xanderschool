#!/usr/bin/env python3
"""Cancel a fee payment with a reason, and allow that cancel to be revoked."""
from __future__ import annotations

import os
import re
import time
from pathlib import Path

import paramiko

ROOT = Path(r"C:\xampp7\htdocs\Xander-school")
HOST = "66.29.135.120"
USER = "root"
_src = (Path(__file__).with_name("_ssh_deploy_discipline_codes.py")).read_text(encoding="utf-8")
_m = re.search(r'PASSWORD = os\.environ\.get\("VPS_PASSWORD", "([^"]*)"\)', _src)
PASSWORD = os.environ.get("VPS_PASSWORD", _m.group(1) if _m else "")
REMOTE_BASE = "/opt/xander-school/app"

FILES = [
    "app/Models/FeesRecordModel.php",
    "app/Controllers/Home.php",
    "app/Views/pages/fees_entry.php",
    "public/assets/css/fees-entry.css",
]


def main() -> int:
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect(HOST, username=USER, password=PASSWORD, timeout=90, banner_timeout=90)
    sftp = c.open_sftp()
    for rel in FILES:
        remote = f"{REMOTE_BASE}/{rel}"
        print("PUT", rel)
        c.exec_command(f"mkdir -p {remote.rsplit('/', 1)[0]}")
        time.sleep(0.05)
        sftp.put(str(ROOT / rel), remote)
    sftp.close()
    cmd = r"""
set -e
cd /opt/xander-school/deploy
docker compose -f docker-compose.prod.yml --env-file .env.production restart app
sleep 4
docker exec xander_school_app php -r 'opcache_reset();'
grep -q cancel_reason /opt/xander-school/app/app/Models/FeesRecordModel.php && echo OK model
grep -q 'Revoke cancel' /opt/xander-school/app/app/Views/pages/fees_entry.php && echo OK view
echo DONE
"""
    _, o, e = c.exec_command(cmd, timeout=180)
    print(o.read().decode(errors="replace"))
    err = e.read().decode(errors="replace")
    for line in err.splitlines():
        if "password" in line.lower():
            continue
        if line.strip():
            print(line)
    c.close()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
