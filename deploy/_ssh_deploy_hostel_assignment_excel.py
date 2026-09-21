#!/usr/bin/env python3
"""Deploy hostel assignment Excel export to VPS."""
from __future__ import annotations

import os
from pathlib import Path

import paramiko

ROOT = Path(r"C:\xampp7\htdocs\Xander-school")
REMOTE_APP = "/opt/xander-school/app"
FILES = [
    "app/Libraries/HostelAssignmentExcelExporter.php",
    "app/Models/HostelSchemaModel.php",
    "app/Controllers/Home.php",
    "app/Config/Routes.php",
    "app/Views/pages/partials/hostels_settings.php",
    "public/assets/css/hostels.css",
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
        "docker exec xander_school_app grep -n 'export_hostel_assignment_excel' "
        "/var/www/html/app/Config/Routes.php /var/www/html/app/Controllers/Home.php | head"
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
