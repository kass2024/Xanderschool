#!/usr/bin/env python3
"""Deploy one student check-in per day and checkout overwrite."""
from __future__ import annotations

import importlib.util
import sys
from pathlib import Path

import paramiko

ROOT = Path(r"C:\xampp7\htdocs\Xander-school")
base = ROOT / "deploy" / "_ssh_deploy_student_circle_fill.py"
spec = importlib.util.spec_from_file_location("circle_deploy", base)
mod = importlib.util.module_from_spec(spec)
spec.loader.exec_module(mod)

FILES = [
    "app/Libraries/AttendanceScanService.php",
]


def main() -> int:
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect(mod.HOST, username=mod.USER, password=mod.PASSWORD, timeout=180, banner_timeout=180, auth_timeout=180)
    sftp = c.open_sftp()
    for rel in FILES:
        remote = f"{mod.REMOTE_APP}/{rel}"
        sftp.put(str(ROOT / rel), remote)
        print("PUT", rel)
    sftp.close()
    cmd = r"""
set -e
cd /opt/xander-school/deploy
docker compose -f docker-compose.prod.yml --env-file .env.production restart app
sleep 8
docker exec xander_school_app php -r 'opcache_reset();' || true
docker exec xander_school_app php -l /var/www/html/app/Libraries/AttendanceScanService.php
docker exec xander_school_app grep -n "Already In" /var/www/html/app/Libraries/AttendanceScanService.php
echo DONE
"""
    _, o, e = c.exec_command(cmd, timeout=180)
    sys.stdout.buffer.write(o.read())
    err = e.read().decode(errors="replace")
    if err.strip():
        sys.stderr.write(err)
    code = o.channel.recv_exit_status()
    c.close()
    print("EXIT", code)
    return code


if __name__ == "__main__":
    raise SystemExit(main())
