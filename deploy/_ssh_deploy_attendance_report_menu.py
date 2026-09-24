#!/usr/bin/env python3
"""Deploy the Student Attendance report menu."""
from __future__ import annotations

import os
import sys
import time
from pathlib import Path

import paramiko

ROOT = Path(r"C:\xampp7\htdocs\Xander-school")
HOST = "66.29.135.120"
USER = "root"
PASSWORD = os.environ.get("VPS_PASSWORD", "6W7sa2g4dMEwcN80ZU")
REMOTE_APP = "/opt/xander-school/app"

FILES = [
    "app/Helpers/qonics_helper.php",
    "app/Language/en/app.php",
    "app/Language/fr/app.php",
    "app/Views/main.php",
    "app/Views/main2.php",
    "app/Views/pages/reports/_attendance_report_type.php",
    "app/Views/pages/reports/student_course_report.php",
    "app/Views/pages/reports/student_class_daily_report.php",
    "app/Views/pages/reports/student_daily_report.php",
    "app/Views/pages/reports/student_general_daily_report.php",
    "app/Views/pages/reports/student_boarding_report.php",
    "app/Views/pages/reports/student_general_boarding_report.php",
    "app/Views/pages/reports/student_inout_report_monthly.php",
]


def _deploy_once() -> int:
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect(HOST, username=USER, password=PASSWORD, timeout=120, banner_timeout=120, auth_timeout=120)
    sftp = c.open_sftp()
    for rel in FILES:
        local = ROOT / rel
        if not local.is_file():
            print("MISSING", rel)
            return 1
        remote = f"{REMOTE_APP}/{rel.replace(chr(92), '/')}"
        print("PUT", rel, local.stat().st_size)
        sftp.put(str(local), remote)
    sftp.close()
    cmd = r"""
set -e
cd /opt/xander-school/deploy
docker compose -f docker-compose.prod.yml --env-file .env.production restart app
sleep 6
docker exec xander_school_app php -r 'opcache_reset();'
docker exec xander_school_app grep -n "attendanceReport\|attendanceReportType\|Student IN/OUT Attendance" \
  /var/www/html/app/Views/main.php \
  /var/www/html/app/Views/pages/reports/_attendance_report_type.php \
  /var/www/html/app/Helpers/qonics_helper.php | head -40
echo DONE
"""
    _, o, e = c.exec_command(cmd, timeout=240)
    sys.stdout.buffer.write(o.read())
    sys.stderr.buffer.write(e.read())
    code = o.channel.recv_exit_status()
    c.close()
    print("EXIT", code)
    return code


def main() -> int:
    last_err: Exception | None = None
    for attempt in range(1, 6):
        try:
            print(f"attempt {attempt}")
            return _deploy_once()
        except Exception as ex:  # noqa: BLE001
            last_err = ex
            print("fail", type(ex).__name__, ex)
            time.sleep(8)
    print("ALL FAILED", last_err)
    return 1


if __name__ == "__main__":
    raise SystemExit(main())
