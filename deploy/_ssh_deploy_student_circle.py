#!/usr/bin/env python3
"""Fit Wisdom student photos inside the artwork circle."""
from __future__ import annotations

import re
import time
from pathlib import Path

import paramiko

ROOT = Path(r"C:\xampp7\htdocs\Xander-school")
HOST = "66.29.135.120"
USER = "root"
_src = (ROOT / "deploy" / "_ssh_deploy_discipline_codes.py").read_text(encoding="utf-8")
_m = re.search(r'PASSWORD = os\.environ\.get\("VPS_PASSWORD", "([^"]*)"\)', _src)
PASSWORD = _m.group(1) if _m else ""
REMOTE_BASE = "/opt/xander-school/app"
FILES = [
    "app/Libraries/WisdomCardRenderer.php",
    "app/Libraries/ProfilePhotoNormalizer.php",
    "app/Libraries/CardLayout.php",
    "app/Views/templates/student_card_smart.php",
    "app/Helpers/qonics_helper.php",
]
VERIFY = [
    ("app/Libraries/WisdomCardRenderer.php", "HOLE_D = 245"),
    ("app/Helpers/qonics_helper.php", "v15fitcircle"),
    ("app/Views/templates/student_card_smart.php", "object-position: center center"),
]


def main() -> int:
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect(HOST, username=USER, password=PASSWORD, timeout=90, banner_timeout=90)
    sftp = c.open_sftp()
    for rel in FILES:
        local = ROOT / rel
        remote = f"{REMOTE_BASE}/{rel}"
        print("PUT", rel)
        c.exec_command(f"mkdir -p {remote.rsplit('/', 1)[0]}")
        time.sleep(0.05)
        sftp.put(str(local), remote)
    sftp.close()
    cmd = r"""
set -e
cd /opt/xander-school/deploy
docker compose -f docker-compose.prod.yml --env-file .env.production restart app
sleep 6
docker exec xander_school_app php -r 'opcache_reset();'
"""
    for rel, needle in VERIFY:
        cmd += f'docker exec xander_school_app grep -n "{needle}" /var/www/html/{rel} | head -2\n'
    cmd += "echo DONE\n"
    _, o, e = c.exec_command(cmd, timeout=240)
    print(o.read().decode("utf-8", "replace"))
    err = e.read().decode("utf-8", "replace")
    if err.strip():
        print("STDERR", err)
    code = o.channel.recv_exit_status()
    c.close()
    return code


if __name__ == "__main__":
    raise SystemExit(main())
