#!/usr/bin/env python3
"""Clear HABARUREMA SIMON as extra-fee recorder on WISDOM SCHOOL RWANDA only."""
from __future__ import annotations

import os
from pathlib import Path

import paramiko

ROOT = Path(r"C:\xampp7\htdocs\Xander-school")
HOST = "66.29.135.120"
USER = "root"
PASSWORD = os.environ.get("VPS_PASSWORD", "6W7sa2g4dMEwcN80ZU")
REMOTE_BASE = "/opt/xander-school/app"
FILES = [
    "app/Models/ExtraFeesModel.php",
    "app/Controllers/Home.php",
    "app/Views/pages/extra_fees_management.php",
    "deploy/seed_wisdom_rwanda_extra_fees.php",
]
VERIFY = [
    ("app/Models/ExtraFeesModel.php", "clearForeignCreatedByForWisdomRwanda"),
    ("app/Models/ExtraFeesModel.php", "isWisdomSchoolRwanda"),
    ("app/Controllers/Home.php", "clearForeignCreatedByForWisdomRwanda"),
]

SQL = r"""
UPDATE extra_fees ef
LEFT JOIN staffs st ON st.id = ef.created_by AND st.school_id = ef.school_id
SET ef.created_by = NULL
WHERE ef.school_id = 27
  AND ef.created_by IS NOT NULL
  AND ef.created_by <> 0
  AND st.id IS NULL;

SELECT ef.created_by, TRIM(CONCAT(COALESCE(st.fname,''),' ',COALESCE(st.lname,''))) name, st.school_id staff_school, COUNT(*) n
FROM extra_fees ef
LEFT JOIN staffs st ON st.id = ef.created_by
WHERE ef.school_id = 27
GROUP BY ef.created_by, name, staff_school
ORDER BY n DESC;
"""


def main() -> int:
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect(HOST, username=USER, password=PASSWORD, timeout=90, banner_timeout=90)
    sftp = c.open_sftp()
    for rel in FILES:
        remote = f"{REMOTE_BASE}/{rel}"
        print("PUT", rel)
        sftp.put(str(ROOT / rel), remote)
    sftp.close()
    verify = "\n".join(
        f'grep -q "{needle}" "{REMOTE_BASE}/{path}" || exit 1' for path, needle in VERIFY
    )
    cmd = f"""
set -e
cd /opt/xander-school/deploy
docker compose -f docker-compose.prod.yml --env-file .env.production restart app
sleep 3
docker exec xander_school_app php -r 'opcache_reset();' || true
{verify}
echo DONE
"""
    _, o, e = c.exec_command(cmd, timeout=240)
    out = o.read().decode()
    err = e.read().decode()
    print(out)
    if err:
        print(err)
    if "DONE" not in out:
        c.close()
        return 1
    stdin, o2, e2 = c.exec_command(
        "docker exec -i xander_school_mysql mysql -uxander_school -pXsApp_3hT6yU1bC5nM9 iotxa_db -t",
        timeout=90,
    )
    stdin.write(SQL)
    stdin.channel.shutdown_write()
    print(o2.read().decode("utf-8", "replace"))
    err2 = e2.read().decode("utf-8", "replace")
    if err2.strip() and "Using a password" not in err2:
        print(err2)
    c.close()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
