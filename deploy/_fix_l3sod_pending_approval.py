#!/usr/bin/env python3
"""Repair the stale Wisdom pending-registration class mapping for L3 SOD."""
from __future__ import annotations

import os
import sys

import paramiko

HOST = "66.29.135.120"
USER = "root"
PASSWORD = os.environ.get("VPS_PASSWORD", "6W7sa2g4dMEwcN80ZU")

UPDATE_SQL = r"""
UPDATE applications
SET class_id = 220,
    level = 1,
    department_id = 73,
    faculty_id = 11
WHERE schoolId = 27
  AND id = 48;
"""

VERIFY_SQL = r"""
SELECT a.id,
       CONCAT(a.fname, ' ', a.lname) AS applicant,
       a.class_id,
       l.title AS level_name,
       d.title AS dept_name,
       d.code AS dept_code,
       f.title AS faculty_name,
       a.studyingMode
FROM applications a
LEFT JOIN levels l ON l.id = a.level
LEFT JOIN departments d ON d.id = a.department_id
LEFT JOIN faculty f ON f.id = a.faculty_id
WHERE a.id = 48;

SELECT ex.id,
       ex.title,
       ex.amount,
       ex.amount_boarding,
       ex.amount_day,
       ex.term,
       ex.academic_year
FROM extra_fees ex
WHERE ex.school_id = 27
  AND ex.type = 0
  AND ex.type_id = 220
  AND ex.title LIKE '%Registration%'
ORDER BY ex.term ASC, ex.id ASC;
"""


def run_sql(client: paramiko.SSHClient, sql: str) -> str:
    stdin, stdout, stderr = client.exec_command(
        "docker exec -i xander_school_mysql mysql -uxander_school -pXsApp_3hT6yU1bC5nM9 iotxa_db -t",
        timeout=120,
    )
    stdin.write(sql)
    stdin.channel.shutdown_write()
    out = stdout.read().decode("utf-8", "replace")
    err = stderr.read().decode("utf-8", "replace")
    if err.strip() and "Using a password" not in err:
        raise RuntimeError(err.strip())
    return out


def main() -> int:
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(HOST, username=USER, password=PASSWORD, timeout=60, banner_timeout=60)
    try:
        print("Applying Wisdom L3 SOD pending approval repair...")
        print(run_sql(client, UPDATE_SQL))
        print("Verifying application mapping and registration fee...")
        print(run_sql(client, VERIFY_SQL))
    finally:
        client.close()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
