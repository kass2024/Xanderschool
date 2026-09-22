#!/usr/bin/env python3
"""Keep auto extra fees (Feeding/Transport/Registration) on WISDOM SCHOOL RWANDA only."""
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
]
VERIFY = [
    ("app/Models/ExtraFeesModel.php", "if (!self::isWisdomSchoolRwanda($schoolId))"),
    ("app/Controllers/Home.php", "if (ExtraFeesModel::isWisdomSchoolRwanda((int) $school_id))"),
    ("app/Controllers/Home.php", "ensureDayScholarFeedingTransport"),
]

SQL = r"""
SELECT ef.school_id, sc.name school, ef.title, COUNT(*) n
FROM extra_fees ef
LEFT JOIN schools sc ON sc.id = ef.school_id
WHERE ef.school_id <> 27
  AND (ef.title LIKE '%feeding%' OR ef.title LIKE '%transport%')
GROUP BY ef.school_id, sc.name, ef.title
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
