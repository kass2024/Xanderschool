#!/usr/bin/env python3
"""Put the Runda staff-card artwork and renderer on the VPS."""
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
    "app/Libraries/WisdomStaffCardRenderer.php",
    "public/assets/images/background/wisdom_staff_card_runda.png",
]


def main() -> int:
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(HOST, username=USER, password=PASSWORD, timeout=90, banner_timeout=90)
    sftp = client.open_sftp()
    for rel in FILES:
        local = ROOT / rel
        remote = f"{REMOTE_BASE}/{rel}".replace("\\", "/")
        print("PUT", rel, local.stat().st_size)
        client.exec_command(f"mkdir -p {remote.rsplit('/', 1)[0]}")
        time.sleep(0.05)
        sftp.put(str(local), remote)
    sftp.close()
    cmd = f"""
cd /opt/xander-school/deploy
docker compose -f docker-compose.prod.yml --env-file .env.production restart app
sleep 3
docker exec xander_school_app php -r 'if (function_exists("opcache_reset")) opcache_reset();'
docker exec xander_school_app php -l /var/www/html/app/Libraries/WisdomStaffCardRenderer.php
grep -q TEMPLATE_RUNDA {REMOTE_BASE}/app/Libraries/WisdomStaffCardRenderer.php && echo OK renderer
test -s {REMOTE_BASE}/public/assets/images/background/wisdom_staff_card_runda.png && echo OK runda
echo DONE
"""
    _in, stdout, stderr = client.exec_command(cmd, timeout=240)
    out = stdout.read().decode("utf-8", "replace")
    err = stderr.read().decode("utf-8", "replace")
    print(out)
    if err.strip():
        print(err[-1500:])
    client.close()
    return 0 if "OK runda" in out and "DONE" in out else 1


if __name__ == "__main__":
    raise SystemExit(main())
