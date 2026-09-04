#!/usr/bin/env python3
"""List Wisdom Rwanda classes + required materials (prod)."""
from __future__ import annotations

import os
import sys

import paramiko

HOST = "66.29.135.120"
USER = "root"
PASSWORD = os.environ.get("VPS_PASSWORD", "6W7sa2g4dMEwcN80ZU")
SQL = """
SELECT c.id, l.title AS lvl, TRIM(c.title) AS stream, IFNULL(d.code,'') AS code, IFNULL(d.title,'') AS dept
FROM classes c
JOIN levels l ON l.id=c.level
LEFT JOIN departments d ON d.id=c.department
WHERE c.school_id=27
ORDER BY l.title, d.code, c.title;
SELECT id, title FROM academic_year WHERE school_id=27 ORDER BY id DESC LIMIT 5;
SELECT id, name, unit FROM required_materials WHERE school_id=27 AND active=1 ORDER BY name;
SELECT class_id, COUNT(*) n FROM class_required_materials WHERE school_id=27 GROUP BY class_id;
"""


def main() -> int:
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(HOST, username=USER, password=PASSWORD, timeout=90, banner_timeout=90)
    cmd = "docker exec -i xander_school_mysql mysql -uxander_school -pXsApp_3hT6yU1bC5nM9 iotxa_db"
    stdin, stdout, stderr = client.exec_command(cmd, timeout=60)
    stdin.write(SQL)
    stdin.channel.shutdown_write()
    sys.stdout.write(stdout.read().decode("utf-8", "replace"))
    err = stderr.read().decode("utf-8", "replace")
    if err.strip() and "Using a password" not in err:
        sys.stderr.write(err)
    client.close()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
