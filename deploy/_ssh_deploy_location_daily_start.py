#!/usr/bin/env python3
"""Daily start switch for each student IN/OUT attendance location."""
from __future__ import annotations

import os
import time
from pathlib import Path

import paramiko

ROOT = Path(r"C:\xampp7\htdocs\Xander-school")
HOST = "66.29.135.120"
USER = "root"
REMOTE_BASE = "/opt/xander-school/app"

FILES = [
    "app/Models/AttendanceAreaModel.php",
    "app/Controllers/Home.php",
    "app/Controllers/Api.php",
    "app/Libraries/AttendanceScanService.php",
    "app/Views/pages/partials/attendance_areas_settings.php",
    "app/Views/attendance_card.php",
]

VERIFY = [
    ("app/Models/AttendanceAreaModel.php", "function saveSchedule"),
    ("app/Controllers/Home.php", "action === 'schedule'"),
    ("app/Controllers/Api.php", "locationPayload"),
    ("app/Libraries/AttendanceScanService.php", "notStartedMessage"),
    ("app/Views/pages/partials/attendance_areas_settings.php", "Daily start"),
    ("app/Views/attendance_card.php", "data-scheduled"),
]


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
    sftp = c.open_sftp()
    for rel in FILES:
        local = ROOT / rel
        remote = f"{REMOTE_BASE}/{rel}".replace("\\", "/")
        print("PUT", rel, local.stat().st_size)
        c.exec_command(f"mkdir -p {remote.rsplit('/', 1)[0]}")
        time.sleep(0.05)
        sftp.put(str(local), remote)
    sftp.close()

    verify = "\n".join(
        f'grep -q "{needle}" "{REMOTE_BASE}/{path}" || {{ echo MISSING {path}; exit 1; }}'
        for path, needle in VERIFY
    )
    cmd = f"""
set -e
cd /opt/xander-school/deploy
docker compose -f docker-compose.prod.yml --env-file .env.production restart app
sleep 6
docker exec xander_school_app php -r 'if (function_exists("opcache_reset")) {{ opcache_reset(); echo "opcache\\n"; }}'
docker exec xander_school_app php -r 'require "/var/www/html/app/Config/MenuClearance.php"; echo (\\Config\\MenuClearance::isChiefAccountantPost(28) ? "chief_ok\\n" : "chief_bad\\n"); echo (method_exists("Config\\\\MenuClearance","canViewFamilies") ? "families_ok\\n" : "families_bad\\n"); echo (method_exists("Config\\\\MenuClearance","withoutMarksMenus") ? "marks_ok\\n" : "marks_bad\\n");'
{verify}
docker exec xander_school_app php -r 'define("FCPATH","/var/www/html/public/"); require "/var/www/html/app/Config/Paths.php"; $paths=new Config\\Paths(); chdir(FCPATH); $app=require rtrim($paths->systemDirectory,"/ ")."/bootstrap.php"; (new App\\Models\\AttendanceAreaModel())->ensureSchema(); $f=Config\\Database::connect()->getFieldNames("attendance_areas"); echo (in_array("schedule_on",$f,true)?"schedule_on_ok\\n":"schedule_on_bad\\n"); echo (in_array("daily_start",$f,true)?"daily_start_ok\\n":"daily_start_bad\\n");'
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
    _, o, e = c.exec_command(cmd, timeout=240)
    out = o.read().decode("utf-8", "replace")
    err = e.read().decode("utf-8", "replace")
    code = o.channel.recv_exit_status()
    print(out[-4000:])
    if err.strip():
        print("STDERR", err[-2000:].encode("ascii", "replace").decode("ascii"))
    c.close()
    print("exit", code)
    ok = (
        "DONE" in out
        and "login:200" in out
        and "chief_ok" in out
        and "families_ok" in out
        and "marks_ok" in out
        and "schedule_on_ok" in out
        and "daily_start_ok" in out
        and "login:500" not in out
        and "reset:500" not in out
    )
    return 0 if ok else 1


if __name__ == "__main__":
    raise SystemExit(main())
