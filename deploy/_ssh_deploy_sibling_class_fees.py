#!/usr/bin/env python3
"""Copy missing school fees from sibling classes, then deploy the same check."""
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
    "app/Models/SchoolFeesModel.php",
    "app/Controllers/Home.php",
]

SQL = r"""
INSERT INTO school_fees
  (school_id, level, department, class_id, amount, amount_boarding, amount_day, term, academic_year, created_by, created_at, updated_at)
SELECT c.school_id, c.level, c.department, c.id,
       src.amount, src.amount_boarding, src.amount_day, src.term, src.academic_year,
       src.created_by, NOW(), NOW()
FROM classes c
JOIN schools sch ON sch.id = c.school_id
JOIN active_term at ON at.id = sch.active_term
JOIN levels l ON l.id = c.level
JOIN departments d ON d.id = c.department
LEFT JOIN faculty f ON f.id = d.faculty_id
JOIN (
  SELECT sf.school_id, sf.level, sf.department, sf.term, sf.academic_year,
         MIN(sf.amount) AS amount,
         MIN(sf.amount_boarding) AS amount_boarding,
         MIN(sf.amount_day) AS amount_day,
         MIN(sf.created_by) AS created_by,
         COUNT(DISTINCT CONCAT(sf.amount,'|',IFNULL(sf.amount_boarding,'n'),'|',IFNULL(sf.amount_day,'n'))) AS variants
  FROM school_fees sf
  JOIN classes sc2 ON sc2.id = sf.class_id
  JOIN levels sl ON sl.id = sc2.level
  JOIN departments sd ON sd.id = sc2.department
  LEFT JOIN faculty sf2 ON sf2.id = sd.faculty_id
  WHERE sf.class_id > 0
    AND IFNULL(sc2.title,'') NOT LIKE '%Holiday%'
    AND IFNULL(sl.title,'') NOT LIKE '%Holiday%'
    AND IFNULL(sd.title,'') NOT LIKE '%Holiday%'
    AND IFNULL(sd.code,'') NOT LIKE '%Holiday%'
    AND IFNULL(sf2.title,'') NOT LIKE '%Holiday%'
  GROUP BY sf.school_id, sf.level, sf.department, sf.term, sf.academic_year
  HAVING variants = 1
) src ON src.school_id = c.school_id AND src.academic_year = at.academic_year
     AND src.level = c.level AND src.department = c.department
WHERE IFNULL(c.title,'') NOT LIKE '%Holiday%'
  AND IFNULL(l.title,'') NOT LIKE '%Holiday%'
  AND IFNULL(d.title,'') NOT LIKE '%Holiday%'
  AND IFNULL(d.code,'') NOT LIKE '%Holiday%'
  AND IFNULL(f.title,'') NOT LIKE '%Holiday%'
  AND NOT EXISTS (
    SELECT 1 FROM school_fees x
    WHERE x.school_id = c.school_id AND x.academic_year = src.academic_year AND x.term = src.term AND x.class_id = c.id
  )
  AND NOT EXISTS (
    SELECT 1 FROM school_fees y
    WHERE y.school_id = c.school_id AND y.academic_year = src.academic_year AND y.term = src.term
      AND y.level = c.level AND y.department = c.department
      AND (y.class_id IS NULL OR y.class_id = 0)
  );
SELECT ROW_COUNT() AS inserted;
SELECT c.title, sf.term, sf.amount, sf.amount_boarding, sf.amount_day
FROM school_fees sf
JOIN classes c ON c.id = sf.class_id
WHERE sf.class_id IN (270, 271) AND sf.academic_year = 16
ORDER BY c.title, sf.term;
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
grep -q ensureSiblingClassFees /opt/xander-school/app/app/Models/SchoolFeesModel.php && echo OK model
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
