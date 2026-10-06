#!/usr/bin/env python3
"""Deploy the app-style behaviour entry screen and the parent warning SMS."""
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

FILES = [
    "app/Config/Routes.php",
    "app/Controllers/Api.php",
    "app/Controllers/BaseController.php",
    "app/Controllers/Home.php",
    "app/Views/pages/discipline_record_entry.php",
    "public/assets/css/card-scan-ui.css",
]

VERIFY = [
    ("app/Views/pages/discipline_record_entry.php", "disc-action-btn"),
    ("app/Views/pages/discipline_record_entry.php", "Send remarks to parent"),
    ("app/Views/pages/discipline_record_entry.php", "Reduce discipline marks"),
    ("app/Controllers/BaseController.php", "DISCIPLINARY SANCTION"),
    ("app/Controllers/Home.php", "discipline_occurrence_map"),
    ("public/assets/css/card-scan-ui.css", "disc-step"),
]


def blob(rel: str) -> bytes:
    data = subprocess.check_output(["git", "show", f"{COMMIT}:{rel}"], cwd=ROOT)
    return data


def main() -> int:
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(HOST, username=USER, password=PASSWORD, timeout=90, banner_timeout=90)
    sftp = client.open_sftp()
    try:
        for rel in FILES:
            remote = f"{REMOTE_BASE}/{rel}"
            print("PUT", rel)
            client.exec_command(f"mkdir -p {remote.rsplit('/', 1)[0]}")
            time.sleep(0.05)
            with sftp.file(remote, "w") as fh:
                fh.write(blob(rel))
    finally:
        sftp.close()

    cmd = r"""
set -e
cd /opt/xander-school/deploy
docker compose -f docker-compose.prod.yml --env-file .env.production restart app
sleep 6
docker exec xander_school_app php -r 'if (function_exists("opcache_reset")) { opcache_reset(); echo "opcache_reset OK\n"; }'
"""
    for rel, needle in VERIFY:
        cmd += f'docker exec xander_school_app grep -n "{needle}" /var/www/html/{rel} | head -2\n'
    cmd += r"""
docker exec xander_school_app php -l /var/www/html/app/Controllers/BaseController.php
docker exec xander_school_app php -l /var/www/html/app/Controllers/Home.php
docker exec xander_school_app rm -f /var/www/html/writable/_smoke_conduct_remark.php
echo DONE
"""
    _, o, e = client.exec_command(cmd, timeout=240)
    out = o.read().decode("utf-8", "replace")
    err = e.read().decode("utf-8", "replace")
    print(out)
    if err.strip():
        print(err)
    client.close()
    return 0 if "DONE" in out else 1


if __name__ == "__main__":
    raise SystemExit(main())
