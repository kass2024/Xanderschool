#!/usr/bin/env python3
"""Alice Namahoro: Pocket Money shows Canteen and Money report only."""
from __future__ import annotations

import os
import time
from pathlib import Path

import paramiko

ROOT = Path(r"C:\xampp7\htdocs\Xander-school")
HOST = "66.29.135.120"
USER = "root"
REMOTE_BASE = "/opt/xander-school/app"
REL = "app/Helpers/qonics_helper.php"


def password() -> str:
    env = os.environ.get("VPS_PASSWORD", "").strip()
    if env:
        return env
    sibling = Path(__file__).with_name("_ssh_deploy_discipline_codes.py").read_text(encoding="utf-8")
    marker = 'os.environ.get("VPS_PASSWORD", "'
    start = sibling.find(marker)
    if start < 0:
        raise SystemExit("missing VPS password")
    rest = sibling[start + len(marker):]
    return rest.split('")', 1)[0]


def main() -> int:
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect(HOST, username=USER, password=password(), timeout=90, banner_timeout=90)
    remote = f"{REMOTE_BASE}/{REL}"
    sftp = c.open_sftp()
    print("PUT", REL)
    sftp.put(str(ROOT / REL), remote)
    sftp.close()
    cmd = f"""
set -e
grep -q "pocketKeep" "{remote}"
cd /opt/xander-school/deploy
docker compose -f docker-compose.prod.yml --env-file .env.production restart app
sleep 8
docker exec xander_school_app php -r 'if (function_exists("opcache_reset")) {{ opcache_reset(); echo "opcache\\n"; }}'
docker exec xander_school_app php -r 'require "/var/www/html/app/Config/MenuClearance.php"; echo (\\Config\\MenuClearance::isChiefAccountantPost(28) ? "chief_ok\\n" : "chief_bad\\n"); echo (method_exists("Config\\\\MenuClearance","canViewFamilies") ? "families_ok\\n" : "families_bad\\n"); echo (method_exists("Config\\\\MenuClearance","withoutMarksMenus") ? "marks_ok\\n" : "marks_bad\\n");'
login=000
for i in 1 2 3 4 5 6 7 8 9 10 11 12; do
  login=$(docker exec xander_school_app curl -sS -o /dev/null -w '%{{http_code}}' http://127.0.0.1/login || true)
  echo login:$login
  if [ "$login" = "200" ]; then break; fi
  sleep 4
done
reset=$(docker exec xander_school_app curl -sS -o /dev/null -w '%{{http_code}}' http://127.0.0.1/forget/reset || true)
echo reset:$reset
test "$login" = "200"
test "$reset" != "500"
echo DONE
"""
    _, o, e = c.exec_command(cmd, timeout=180)
    out = o.read().decode("utf-8", "replace")
    err = e.read().decode("utf-8", "replace")
    print(out[-2500:])
    if err.strip():
        print("STDERR", err[-800:].encode("ascii", "replace").decode("ascii"))
    c.close()
    ok = "DONE" in out and "login:200" in out and "chief_ok" in out and "families_ok" in out and "marks_ok" in out
    return 0 if ok else 1


if __name__ == "__main__":
    raise SystemExit(main())
