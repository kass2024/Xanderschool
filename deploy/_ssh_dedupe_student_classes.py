#!/usr/bin/env python3
"""Keep one regular class per student per year; delete leftover enrollments."""
from __future__ import annotations

import os

import paramiko

HOST = "66.29.135.120"
USER = "root"
PASSWORD = os.environ.get("VPS_PASSWORD", "6W7sa2g4dMEwcN80ZU")

HOLIDAY = (
    "LOWER(CONCAT(IFNULL(c.title,''),' ',IFNULL(l.title,''),' ',"
    "IFNULL(d.title,''),' ',IFNULL(d.code,''))) LIKE '%holiday%'"
)

SQL = f"""
SELECT 'before_regular_dup_any' AS k, COUNT(*) AS n FROM (
  SELECT cr.student, cr.year
  FROM class_records cr
  JOIN classes c ON c.id = cr.class
  LEFT JOIN levels l ON l.id = c.level
  LEFT JOIN departments d ON d.id = c.department
  WHERE NOT ({HOLIDAY})
  GROUP BY cr.student, cr.year
  HAVING COUNT(DISTINCT cr.class) > 1
) x
UNION ALL
SELECT 'before_regular_dup_active', COUNT(*) FROM (
  SELECT cr.student, cr.year
  FROM class_records cr
  JOIN classes c ON c.id = cr.class
  LEFT JOIN levels l ON l.id = c.level
  LEFT JOIN departments d ON d.id = c.department
  WHERE cr.status = 1 AND NOT ({HOLIDAY})
  GROUP BY cr.student, cr.year
  HAVING COUNT(DISTINCT cr.class) > 1
) x;

DROP TEMPORARY TABLE IF EXISTS tmp_keep_regular;
CREATE TEMPORARY TABLE tmp_keep_regular AS
SELECT CAST(SUBSTRING_INDEX(GROUP_CONCAT(cr.id ORDER BY (cr.status = 1) DESC, cr.id DESC), ',', 1) AS UNSIGNED) AS keep_id,
       cr.student AS student,
       cr.year AS year
FROM class_records cr
INNER JOIN classes c ON c.id = cr.class
LEFT JOIN levels l ON l.id = c.level
LEFT JOIN departments d ON d.id = c.department
WHERE NOT ({HOLIDAY})
GROUP BY cr.student, cr.year
HAVING COUNT(*) > 1;

SELECT COUNT(*) AS keep_groups FROM tmp_keep_regular;

DELETE cr FROM class_records cr
INNER JOIN tmp_keep_regular k ON k.student = cr.student AND k.year = cr.year
INNER JOIN classes c ON c.id = cr.class
LEFT JOIN levels l ON l.id = c.level
LEFT JOIN departments d ON d.id = c.department
WHERE cr.id <> k.keep_id
  AND NOT ({HOLIDAY});

SELECT ROW_COUNT() AS deleted_regular_extras;

DROP TEMPORARY TABLE IF EXISTS tmp_keep_holiday;
CREATE TEMPORARY TABLE tmp_keep_holiday AS
SELECT CAST(SUBSTRING_INDEX(GROUP_CONCAT(cr.id ORDER BY (cr.status = 1) DESC, cr.id DESC), ',', 1) AS UNSIGNED) AS keep_id,
       cr.student AS student,
       cr.year AS year
FROM class_records cr
INNER JOIN classes c ON c.id = cr.class
LEFT JOIN levels l ON l.id = c.level
LEFT JOIN departments d ON d.id = c.department
WHERE {HOLIDAY}
GROUP BY cr.student, cr.year
HAVING COUNT(*) > 1;

DELETE cr FROM class_records cr
INNER JOIN tmp_keep_holiday k ON k.student = cr.student AND k.year = cr.year
INNER JOIN classes c ON c.id = cr.class
LEFT JOIN levels l ON l.id = c.level
LEFT JOIN departments d ON d.id = c.department
WHERE cr.id <> k.keep_id
  AND {HOLIDAY};

SELECT ROW_COUNT() AS deleted_holiday_extras;

SELECT 'after_regular_dup_any' AS k, COUNT(*) AS n FROM (
  SELECT cr.student, cr.year
  FROM class_records cr
  JOIN classes c ON c.id = cr.class
  LEFT JOIN levels l ON l.id = c.level
  LEFT JOIN departments d ON d.id = c.department
  WHERE NOT ({HOLIDAY})
  GROUP BY cr.student, cr.year
  HAVING COUNT(DISTINCT cr.class) > 1
) x
UNION ALL
SELECT 'after_regular_dup_active', COUNT(*) FROM (
  SELECT cr.student, cr.year
  FROM class_records cr
  JOIN classes c ON c.id = cr.class
  LEFT JOIN levels l ON l.id = c.level
  LEFT JOIN departments d ON d.id = c.department
  WHERE cr.status = 1 AND NOT ({HOLIDAY})
  GROUP BY cr.student, cr.year
  HAVING COUNT(DISTINCT cr.class) > 1
) x
UNION ALL
SELECT 'after_all_dup_student_years', COUNT(*) FROM (
  SELECT cr.student, cr.year
  FROM class_records cr
  GROUP BY cr.student, cr.year
  HAVING COUNT(DISTINCT cr.class) > 1
) x;
"""


def main() -> int:
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect(HOST, username=USER, password=PASSWORD, timeout=90, banner_timeout=90)
    stdin, stdout, stderr = c.exec_command(
        "docker exec -i xander_school_mysql mysql -uxander_school -pXsApp_3hT6yU1bC5nM9 iotxa_db",
        timeout=180,
    )
    stdin.write(SQL)
    stdin.channel.shutdown_write()
    out = stdout.read().decode(errors="replace")
    err = stderr.read().decode(errors="replace")
    print(out)
    if err:
        print(err)
    status = stdout.channel.recv_exit_status()
    print("EXIT", status)
    c.close()
    return status


if __name__ == "__main__":
    raise SystemExit(main())
