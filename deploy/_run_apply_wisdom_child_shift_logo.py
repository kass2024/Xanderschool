#!/usr/bin/env python3
"""Copy Academic Staffs shift + Wisdom logo onto all Wisdom child schools."""
from __future__ import annotations

import os
import shutil
import sys
from pathlib import Path

import paramiko

ROOT = Path(r"C:\xampp7\htdocs\Xander-school")
HOST = "66.29.135.120"
USER = "root"
PASSWORD = os.environ.get("VPS_PASSWORD", "6W7sa2g4dMEwcN80ZU")
REMOTE_APP = "/opt/xander-school/app"
LOCAL_LOGO_SRC = Path(r"C:\Users\user\Downloads\Wisdom Logo.png")
LOCAL_LOGO_DEST = ROOT / "public" / "assets" / "images" / "logo" / "wisdom_logo.png"
REMOTE_LOGO = f"{REMOTE_APP}/public/assets/images/logo/wisdom_logo.png"
REMOTE_SCRIPT = f"{REMOTE_APP}/deploy/apply_wisdom_child_shift_logo.php"


def main() -> int:
    if not LOCAL_LOGO_SRC.is_file():
        print("Missing", LOCAL_LOGO_SRC)
        return 1
    LOCAL_LOGO_DEST.parent.mkdir(parents=True, exist_ok=True)
    shutil.copy2(LOCAL_LOGO_SRC, LOCAL_LOGO_DEST)
    print("LOCAL COPY", LOCAL_LOGO_DEST, LOCAL_LOGO_DEST.stat().st_size)

    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect(HOST, username=USER, password=PASSWORD, timeout=90, banner_timeout=90)
    sftp = c.open_sftp()
    c.exec_command(f"mkdir -p {REMOTE_APP}/deploy {REMOTE_APP}/public/assets/images/logo")
    sftp.put(str(LOCAL_LOGO_DEST), REMOTE_LOGO)
    print("PUT logo", REMOTE_LOGO)
    sftp.put(str(ROOT / "deploy" / "apply_wisdom_child_shift_logo.php"), REMOTE_SCRIPT)
    print("PUT script")
    sftp.close()

    cmd = r"""
set -e
docker exec xander_school_app php /var/www/html/deploy/apply_wisdom_child_shift_logo.php
echo '--- verify ---'
docker exec xander_school_mysql mysql -uxander_school -pXsApp_3hT6yU1bC5nM9 iotxa_db -t -e "
SELECT sc.id,sc.name,sc.logo,s.id shift_id,s.title,s.options,
  (SELECT COUNT(*) FROM staffs st WHERE st.school_id=sc.id) staffs,
  (SELECT COUNT(*) FROM staffs st WHERE st.school_id=sc.id AND st.shift_id=s.id) assigned
FROM schools sc
LEFT JOIN shifts s ON s.school_id=sc.id AND s.title='Academic Staffs'
WHERE sc.master_school_id=27
ORDER BY sc.id;
"
ls -l /opt/xander-school/app/public/assets/images/logo/wisdom_logo.png
echo DONE
"""
    _, o, e = c.exec_command(cmd, timeout=180)
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
