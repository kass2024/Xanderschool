#!/usr/bin/env python3
"""Copy missing extra fees from sibling classes, then deploy the invoice fix."""
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
    "app/Models/ExtraFeesModel.php",
    "app/Controllers/Home.php",
]

SQL = r"""
INSERT INTO extra_fees
  (school_id, title, academic_year, type_id, type, term, amount, amount_boarding, amount_day, created_by, created_at, updated_at)
SELECT c.school_id, src.title, src.academic_year, c.id, 0, src.term,
       src.amount, src.amount_boarding, src.amount_day, src.created_by, NOW(), NOW()
FROM classes c
JOIN schools sch ON sch.id = c.school_id
JOIN active_term at ON at.id = sch.active_term
JOIN levels l ON l.id = c.level
JOIN departments d ON d.id = c.department
LEFT JOIN faculty f ON f.id = d.faculty_id
JOIN (
  SELECT ef.school_id, c2.level, c2.department, ef.term, ef.academic_year,
         LOWER(TRIM(ef.title)) AS title_key,
         MIN(ef.title) AS title,
         MIN(ef.amount) AS amount,
         MIN(ef.amount_boarding) AS amount_boarding,
         MIN(ef.amount_day) AS amount_day,
         MIN(ef.created_by) AS created_by,
         COUNT(DISTINCT CONCAT(ef.amount,'|',IFNULL(ef.amount_boarding,'n'),'|',IFNULL(ef.amount_day,'n'))) AS variants
  FROM extra_fees ef
  JOIN classes c2 ON c2.id = ef.type_id AND ef.type = 0
  JOIN levels l2 ON l2.id = c2.level
  JOIN departments d2 ON d2.id = c2.department
  LEFT JOIN faculty f2 ON f2.id = d2.faculty_id
  WHERE IFNULL(c2.title,'') NOT LIKE '%Holiday%'
    AND IFNULL(l2.title,'') NOT LIKE '%Holiday%'
    AND IFNULL(d2.title,'') NOT LIKE '%Holiday%'
    AND IFNULL(d2.code,'') NOT LIKE '%Holiday%'
    AND IFNULL(f2.title,'') NOT LIKE '%Holiday%'
  GROUP BY ef.school_id, c2.level, c2.department, ef.term, ef.academic_year, LOWER(TRIM(ef.title))
  HAVING variants = 1 AND MIN(ef.amount) > 0
) src ON src.school_id = c.school_id AND src.academic_year = at.academic_year
     AND src.level = c.level AND src.department = c.department
WHERE IFNULL(c.title,'') NOT LIKE '%Holiday%'
  AND IFNULL(l.title,'') NOT LIKE '%Holiday%'
  AND IFNULL(d.title,'') NOT LIKE '%Holiday%'
  AND IFNULL(d.code,'') NOT LIKE '%Holiday%'
  AND IFNULL(f.title,'') NOT LIKE '%Holiday%'
  AND NOT EXISTS (
    SELECT 1 FROM extra_fees x
    WHERE x.school_id = c.school_id AND x.academic_year = src.academic_year
      AND x.type = 0 AND x.type_id = c.id AND x.term = src.term
      AND LOWER(TRIM(x.title)) = src.title_key
  );
SELECT ROW_COUNT() AS inserted;
SELECT c.title AS class_title, ef.title, ef.term, ef.amount, ef.amount_boarding, ef.amount_day
FROM extra_fees ef
JOIN classes c ON c.id = ef.type_id
WHERE ef.type = 0 AND ef.type_id = 270 AND ef.academic_year = 16
ORDER BY ef.term, ef.title;
"""


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
grep -q ensureSiblingClassExtras /opt/xander-school/app/app/Models/ExtraFeesModel.php && echo OK model
grep -q sharesAmountAcrossModes /opt/xander-school/app/app/Models/ExtraFeesModel.php && echo OK amounts
ENVF=/opt/xander-school/deploy/.env.production
DB=$(grep -E '^DB_DATABASE=' "$ENVF" | head -1 | cut -d= -f2-)
U=$(grep -E '^DB_USERNAME=' "$ENVF" | head -1 | cut -d= -f2-)
P=$(grep -E '^DB_PASSWORD=' "$ENVF" | head -1 | cut -d= -f2-)
docker exec -i xander_school_mysql mysql -u"$U" -p"$P" "$DB" --batch --raw <<'SQL'
""" + SQL + """
SQL
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
