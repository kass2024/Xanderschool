#!/usr/bin/env python3
"""Staff cards keep cool-toned portraits that fail the skin-tone test."""
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
    "app/Libraries/ProfilePhotoNormalizer.php",
]

VERIFY = [
    ("app/Libraries/ProfilePhotoNormalizer.php", "large subject with internal contrast"),
]


def main() -> int:
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect(HOST, username=USER, password=PASSWORD, timeout=90, banner_timeout=90)
    sftp = c.open_sftp()
    for rel in FILES:
        local = ROOT / rel
        remote = f"{REMOTE_BASE}/{rel}".replace("\\", "/")
        print("PUT", rel)
        c.exec_command(f"mkdir -p {remote.rsplit('/', 1)[0]}")
        time.sleep(0.05)
        sftp.put(str(local), remote)
    sftp.close()
    verify = "\n".join(
        f'grep -q -e "{needle}" "{REMOTE_BASE}/{path}" && echo OK {path} || {{ echo MISSING {path}; exit 1; }}'
        for path, needle in VERIFY
    )
    cmd = f"""
cd /opt/xander-school/deploy
docker compose -f docker-compose.prod.yml --env-file .env.production restart app
sleep 3
docker exec xander_school_app php -r 'opcache_reset();' || true
{verify}
docker exec xander_school_app php -r 'require "/var/www/html/app/Libraries/ProfilePhotoNormalizer.php"; $src = imagecreatefromjpeg("/var/www/html/public/assets/images/profile/img_6c241bb9efee6f15.jpg"); $n = new App\\Libraries\\ProfilePhotoNormalizer(); echo $n->isEmptyPortrait($src) ? "empty\\n" : "portrait-ok\\n";'
rm -f /opt/xander-school/app/deploy/_probe_mwiseneza_photo.php
echo DONE
"""
    _, o, e = c.exec_command(cmd, timeout=240)
    out = o.read().decode()
    err = e.read().decode()
    print(out)
    if err:
        print(err)
    c.close()
    return 0 if "portrait-ok" in out and "DONE" in out else 1


if __name__ == "__main__":
    raise SystemExit(main())
