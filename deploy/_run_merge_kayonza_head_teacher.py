#!/usr/bin/env python3
"""Keep a single Kayonza Head Teacher (staff 47) and delete the duplicate."""
from __future__ import annotations

import os
import sys
from pathlib import Path

import paramiko

ROOT = Path(r"C:\xampp7\htdocs\Xander-school")
HOST = "66.29.135.120"
USER = "root"
PASSWORD = os.environ.get("VPS_PASSWORD", "6W7sa2g4dMEwcN80ZU")
REMOTE_APP = "/opt/xander-school/app"
REL = "deploy/merge_kayonza_head_teacher.php"


def main() -> int:
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect(HOST, username=USER, password=PASSWORD, timeout=180, banner_timeout=180, auth_timeout=180)
    sftp = c.open_sftp()
    sftp.put(str(ROOT / REL), f"{REMOTE_APP}/{REL}")
    print("PUT", REL)
    sftp.close()
    cmd = r"""
set -e
docker exec xander_school_app php /var/www/html/deploy/merge_kayonza_head_teacher.php
echo '--- verify ---'
docker exec xander_school_mysql mysql -uxander_school -pXsApp_3hT6yU1bC5nM9 iotxa_db -t -e "
SELECT id,fname,lname,email,phone,post,status FROM staffs WHERE school_id=35 AND post=25;
SELECT id,fname,lname FROM staffs WHERE id=105;
SELECT COUNT(*) mentors_on_47 FROM classes WHERE school_id=35 AND mentor=47;
SELECT COUNT(*) mentors_on_105 FROM classes WHERE school_id=35 AND mentor=105;
"
echo DONE
"""
    _, o, e = c.exec_command(cmd, timeout=120)
    sys.stdout.buffer.write(o.read())
    err = e.read().decode(errors="replace")
    if err.strip() and "Using a password" not in err:
        sys.stderr.write(err)
    code = o.channel.recv_exit_status()
    c.close()
    print("EXIT", code)
    return code


if __name__ == "__main__":
    raise SystemExit(main())
