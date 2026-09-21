#!/usr/bin/env python3
"""Deploy discipline holiday filter, searchable codes, and KPI dashboard."""
from __future__ import annotations

import os
from pathlib import Path

import paramiko

ROOT = Path(r"C:\xampp7\htdocs\Xander-school")
HOST = "66.29.135.120"
USER = "root"
PASSWORD = os.environ.get("VPS_PASSWORD", "6W7sa2g4dMEwcN80ZU")
REMOTE_BASE = "/opt/xander-school/app"
FILES = [
    "app/Controllers/Home.php",
    "app/Models/DisciplineCodeModel.php",
    "app/Config/Routes.php",
    "app/Views/pages/discipline_record_entry.php",
    "app/Views/pages/partials/behavior_dashboard.php",
    "public/assets/css/card-scan-ui.css",
]
VERIFY = [
    ("app/Controllers/Home.php", "disciplineStudentsWithoutHoliday"),
    ("app/Views/pages/discipline_record_entry.php", "disc-kpi-grid"),
    ("app/Views/pages/discipline_record_entry.php", "disc_code_filter"),
    ("app/Config/Routes.php", "discipline_codes_json"),
]


def main() -> int:
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect(HOST, username=USER, password=PASSWORD, timeout=90, banner_timeout=90)
    sftp = c.open_sftp()
    for rel in FILES:
        remote = f"{REMOTE_BASE}/{rel}"
        print("PUT", rel)
        sftp.put(str(ROOT / rel), remote)
    sftp.close()
    verify = "\n".join(
        f'grep -q "{needle}" "{REMOTE_BASE}/{path}" || exit 1' for path, needle in VERIFY
    )
    cmd = f"""
set -e
cd /opt/xander-school/deploy
docker compose -f docker-compose.prod.yml --env-file .env.production restart app
sleep 3
docker exec xander_school_app php -r 'opcache_reset();' || true
{verify}
echo DONE
"""
    _, o, e = c.exec_command(cmd, timeout=240)
    out = o.read().decode()
    err = e.read().decode()
    print(out)
    if err:
        print(err)
    c.close()
    return 0 if "DONE" in out else 1


if __name__ == "__main__":
    raise SystemExit(main())
