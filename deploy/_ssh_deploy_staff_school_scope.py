#!/usr/bin/env python3
"""Keep staff cards inside one school and clear UWASE Kevine's card."""
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
    "app/Libraries/CardRegistry.php",
    "app/Libraries/AttendanceScanService.php",
]
VERIFY = [
    ("app/Libraries/CardRegistry.php", "Staff cards stay inside one school"),
    ("app/Libraries/AttendanceScanService.php", "Staff lists stay inside the logged-in school."),
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
    verify = "\n".join(
        f'grep -q "{needle}" "{REMOTE_BASE}/{path}" || exit 1' for path, needle in VERIFY
    )
    cmd = f"""
set -e
cd /opt/xander-school/deploy
set -a
. ./.env.production
set +a
echo '--- before ---'
docker exec xander_school_mysql mysql -N -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" -e "SELECT id, school_id, fname, lname, IF(TRIM(COALESCE(card,''))='','empty','has-card') FROM staffs WHERE UPPER(fname) LIKE '%KEVINE%' OR UPPER(fname) LIKE '%KEVINIE%' OR UPPER(lname) LIKE '%KEVINE%' OR UPPER(lname) LIKE '%KEVINIE%';"
docker exec xander_school_mysql mysql -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" -e "UPDATE staffs SET card = NULL WHERE UPPER(fname) LIKE '%KEVINE%' OR UPPER(fname) LIKE '%KEVINIE%' OR UPPER(lname) LIKE '%KEVINE%' OR UPPER(lname) LIKE '%KEVINIE%';"
echo '--- after ---'
docker exec xander_school_mysql mysql -N -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" -e "SELECT id, school_id, fname, lname, IF(TRIM(COALESCE(card,''))='','empty','has-card') FROM staffs WHERE UPPER(fname) LIKE '%KEVINE%' OR UPPER(fname) LIKE '%KEVINIE%' OR UPPER(lname) LIKE '%KEVINE%' OR UPPER(lname) LIKE '%KEVINIE%';"
docker compose -f docker-compose.prod.yml --env-file .env.production restart app
sleep 3
docker exec xander_school_app php -r 'opcache_reset();' || true
{verify}
echo DONE
"""
    _, o, e = c.exec_command(cmd, timeout=240)
    out = o.read().decode("utf-8", "replace")
    err = e.read().decode("utf-8", "replace")
    print(out)
    if err.strip():
        print(err[-1500:])
    c.close()
    return 0 if "DONE" in out else 1


if __name__ == "__main__":
    raise SystemExit(main())
