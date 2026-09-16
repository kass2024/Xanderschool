#!/usr/bin/env python3
"""Add Customer Care post, assign KAMA SHAZI Irene, rename Cooks to Cooker."""
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

SQL = r"""
SELECT 'BEFORE posts' AS step;
SELECT id, title, status FROM posts
WHERE title IN ('Cooks','Cook','Cooker','Customer Care','Teacher')
   OR title LIKE '%Cook%'
   OR title LIKE '%Customer%'
ORDER BY id;

SELECT 'BEFORE staff' AS step;
SELECT s.id, s.fname, s.lname, s.email, s.phone, s.post, p.title AS post_title, s.status, s.school_id
FROM staffs s
LEFT JOIN posts p ON p.id = s.post
WHERE s.school_id = 27
  AND (
    LOWER(IFNULL(s.email,'')) = 'kamashazi.irene@wisdomschool.rw'
    OR REPLACE(IFNULL(s.phone,''),' ','') LIKE '%783095753%'
    OR (s.fname LIKE '%KAMA%' AND s.lname LIKE '%Irene%')
    OR (s.fname LIKE '%Irene%' AND s.lname LIKE '%KAMA%')
  );

INSERT INTO posts (title, status)
SELECT 'Customer Care', 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM posts WHERE title = 'Customer Care');

UPDATE posts SET title = 'Cooker', status = 1 WHERE title = 'Cooks';
UPDATE posts SET status = 1 WHERE title IN ('Cooker', 'Customer Care');

SET @cc := (SELECT id FROM posts WHERE title = 'Customer Care' ORDER BY id ASC LIMIT 1);

UPDATE staffs
SET post = @cc
WHERE school_id = 27
  AND (
    LOWER(IFNULL(email,'')) = 'kamashazi.irene@wisdomschool.rw'
    OR REPLACE(IFNULL(phone,''),' ','') LIKE '%783095753%'
  );

SELECT 'AFTER posts' AS step;
SELECT id, title, status FROM posts
WHERE title IN ('Cooks','Cook','Cooker','Customer Care')
   OR title LIKE '%Cook%'
   OR title LIKE '%Customer%'
ORDER BY id;

SELECT 'AFTER staff' AS step;
SELECT s.id, s.fname, s.lname, s.email, s.phone, s.post, p.title AS post_title, s.status
FROM staffs s
LEFT JOIN posts p ON p.id = s.post
WHERE s.school_id = 27
  AND (
    LOWER(IFNULL(s.email,'')) = 'kamashazi.irene@wisdomschool.rw'
    OR REPLACE(IFNULL(s.phone,''),' ','') LIKE '%783095753%'
  );

SELECT 'COOK STAFF' AS step;
SELECT s.id, CONCAT(s.fname,' ',s.lname) AS name, p.title
FROM staffs s
JOIN posts p ON p.id = s.post
WHERE s.school_id = 27 AND p.title IN ('Cooks','Cooker')
ORDER BY s.fname;
"""


def main() -> int:
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(HOST, username=USER, password=PASSWORD, timeout=180, banner_timeout=180, auth_timeout=180)
    sftp = client.open_sftp()
    local = ROOT / "app/Models/PostsModel.php"
    remote = "/opt/xander-school/app/app/Models/PostsModel.php"
    sftp.put(str(local), remote)
    print("PUT app/Models/PostsModel.php")
    sftp.close()

    stdin, stdout, stderr = client.exec_command(
        "docker exec -i xander_school_mysql mysql -uxander_school -pXsApp_3hT6yU1bC5nM9 iotxa_db -t",
        timeout=90,
    )
    stdin.write(SQL)
    stdin.channel.shutdown_write()
    sys.stdout.write(stdout.read().decode("utf-8", "replace"))
    err = stderr.read().decode("utf-8", "replace")
    if err.strip() and "Using a password" not in err:
        sys.stderr.write(err)

    cmd = r"""
set -e
docker exec xander_school_app grep -n "Customer Care\|Cooker\|ensurePostByTitle" /var/www/html/app/Models/PostsModel.php | head -n 20
cd /opt/xander-school/deploy && docker compose -f docker-compose.prod.yml --env-file .env.production restart app
sleep 4
docker exec xander_school_app php -r "opcache_reset(); echo 'OPCACHE_RESET_OK\n';"
"""
    _, o, e = client.exec_command(cmd, timeout=180)
    sys.stdout.write(o.read().decode("utf-8", "replace"))
    err2 = e.read().decode("utf-8", "replace")
    if err2.strip():
        sys.stderr.write(err2)
    client.close()
    print("Deploy complete.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
