#!/usr/bin/env python3
"""Deploy student-card generate-count fix to VPS."""
from __future__ import annotations

import os
from pathlib import Path

import paramiko

ROOT = Path(r"C:\xampp7\htdocs\Xander-school")
REMOTE_APP = "/opt/xander-school/app"
FILES = [
    "app/Helpers/qonics_helper.php",
    "app/Controllers/Home.php",
    "app/Views/pages/student_cards.php",
    "app/Libraries/WisdomCardRenderer.php",
    "app/Models/StudentModel.php",
]


def main() -> int:
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(
        "66.29.135.120",
        username="root",
        password=os.environ.get("VPS_PASSWORD", "6W7sa2g4dMEwcN80ZU"),
        timeout=90,
        banner_timeout=90,
    )
    sftp = client.open_sftp()
    for rel in FILES:
        print("PUT", rel)
        sftp.put(str(ROOT / rel), f"{REMOTE_APP}/{rel}")
    sftp.close()
    cmd = (
        "cd /opt/xander-school/deploy && "
        "docker compose -f docker-compose.prod.yml --env-file .env.production restart app && "
        "sleep 4 && "
        "docker exec xander_school_app php -r 'opcache_reset(); echo \"opcache ok\\n\";' && "
        "docker exec xander_school_app grep -n 'student_card_photo_is_printable' "
        "/var/www/html/app/Helpers/qonics_helper.php /var/www/html/app/Views/pages/student_cards.php | head"
    )
    _, stdout, stderr = client.exec_command(cmd, timeout=180)
    print(stdout.read().decode(errors="replace"))
    err = stderr.read().decode(errors="replace")
    if err:
        print(err)
    client.close()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
