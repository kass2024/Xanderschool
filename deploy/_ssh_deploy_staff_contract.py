#!/usr/bin/env python3
"""Staff contract dates, and remove the staff-list SMS test button."""
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
    "app/Config/MenuClearance.php",
    "app/Controllers/Home.php",
    "app/Models/StaffModel.php",
    "app/Views/pages/staffs.php",
    "app/Views/pages/staff.php",
    "app/Views/pages/partials/mdl_staff.php",
    "public/assets/css/staff-share-access.css",
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
grep -q canSetStaffContract /opt/xander-school/app/app/Config/MenuClearance.php && echo OK clearance
grep -q save_staff_contract /opt/xander-school/app/app/Controllers/Home.php && echo OK save
grep -q 'Contract start' /opt/xander-school/app/app/Views/pages/staffs.php && echo OK list
! grep -q btnStaffSmsTest /opt/xander-school/app/app/Views/pages/staffs.php && echo OK test_removed
ENVF=/opt/xander-school/deploy/.env.production
DB=$(grep -E '^DB_DATABASE=' "$ENVF" | head -1 | cut -d= -f2-)
U=$(grep -E '^DB_USERNAME=' "$ENVF" | head -1 | cut -d= -f2-)
P=$(grep -E '^DB_PASSWORD=' "$ENVF" | head -1 | cut -d= -f2-)
HAS=$(docker exec xander_school_mysql mysql -u"$U" -p"$P" "$DB" -N -e "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='staffs' AND COLUMN_NAME='contract_start'")
if [ "$HAS" = "0" ]; then
  docker exec xander_school_mysql mysql -u"$U" -p"$P" "$DB" -e "ALTER TABLE staffs ADD COLUMN contract_start DATE NULL DEFAULT NULL, ADD COLUMN contract_end DATE NULL DEFAULT NULL"
  echo COLUMNS_ADDED
else
  echo COLUMNS_EXIST
fi
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
