#!/usr/bin/env python3
"""Give each Wisdom campus its own staff-card artwork."""
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
    "public/assets/images/background/wisdom_staff_card_burera.png",
    "public/assets/images/background/wisdom_staff_card_fumbwe.png",
    "public/assets/images/background/wisdom_staff_card_kabarore.png",
    "public/assets/images/background/wisdom_staff_card_kanzenze.png",
    "public/assets/images/background/wisdom_staff_card_kayonza.png",
    "public/assets/images/background/wisdom_staff_card_kiramuruzi.png",
    "public/assets/images/background/wisdom_staff_card_musanze.png",
    "public/assets/images/background/wisdom_staff_card_muyumbu.png",
    "public/assets/images/background/wisdom_staff_card_ngororero.png",
    "public/assets/images/background/wisdom_staff_card_nyabihu.png",
    "public/assets/images/background/wisdom_staff_card_nyamasheke.png",
    "public/assets/images/background/wisdom_staff_card_rubavu.png",
    "public/assets/images/background/wisdom_staff_card_rubengera.png",
    "public/assets/images/background/wisdom_staff_card_susa.png",
]


def main() -> int:
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect(HOST, username=USER, password=PASSWORD, timeout=90, banner_timeout=90)
    sftp = c.open_sftp()
    for rel in FILES:
        local = ROOT / rel
        remote = f"{REMOTE_BASE}/{rel}".replace("\\", "/")
        print("PUT", rel, local.stat().st_size)
        c.exec_command(f"mkdir -p {remote.rsplit('/', 1)[0]}")
        time.sleep(0.05)
        sftp.put(str(local), remote)
    sftp.close()
    cmd = f"""
cd /opt/xander-school/deploy
docker compose -f docker-compose.prod.yml --env-file .env.production restart app
sleep 3
docker exec xander_school_app php -r 'opcache_reset();' || true
grep -q RUBAVU {REMOTE_BASE}/app/Libraries/WisdomStaffCardRenderer.php && echo OK renderer
test -s {REMOTE_BASE}/public/assets/images/background/wisdom_staff_card_rubavu.png && echo OK rubavu
test -s {REMOTE_BASE}/public/assets/images/background/wisdom_staff_card_fumbwe.png && echo OK fumbwe
echo DONE
"""
    _, o, e = c.exec_command(cmd, timeout=240)
    out = o.read().decode()
    err = e.read().decode()
    print(out)
    if err:
        print(err)
    c.close()
    return 0 if "OK rubavu" in out and "DONE" in out else 1


if __name__ == "__main__":
    raise SystemExit(main())
