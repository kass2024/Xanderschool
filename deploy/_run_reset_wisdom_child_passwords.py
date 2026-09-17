#!/usr/bin/env python3
"""Reset Wisdom CHILD school default passwords on VPS and download credentials .txt."""
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
REMOTE_BASE = "/opt/xander-school/app"

FILES = [
    "app/Services/SchoolHierarchyService.php",
    "deploy/reset_wisdom_child_passwords.php",
    "deploy/seed_wisdom_schools.php",
]


def main() -> int:
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect(HOST, username=USER, password=PASSWORD, timeout=60)
    sftp = c.open_sftp()
    for rel in FILES:
        local = ROOT / rel
        remote = f"{REMOTE_BASE}/{rel}".replace("\\", "/")
        print("PUT", rel)
        sys.stdout.flush()
        c.exec_command(f"mkdir -p {remote.rsplit('/', 1)[0]}")
        time.sleep(0.1)
        sftp.put(str(local), remote)

    print("--- Running child password reset on VPS ---")
    sys.stdout.flush()
    _, o, e = c.exec_command(
        "docker cp {0}/app/Services/SchoolHierarchyService.php xander_school_app:/var/www/html/app/Services/SchoolHierarchyService.php && "
        "docker cp {0}/deploy/reset_wisdom_child_passwords.php xander_school_app:/var/www/html/deploy/reset_wisdom_child_passwords.php && "
        "docker exec xander_school_app php /var/www/html/deploy/reset_wisdom_child_passwords.php".format(REMOTE_BASE),
        timeout=300,
    )
    out = o.read().decode(errors="replace")
    err = e.read().decode(errors="replace")
    sys.stdout.write(out)
    if err.strip():
        sys.stderr.write(err)
    code = o.channel.recv_exit_status()
    print("EXIT", code)

    remote_txt = f"{REMOTE_BASE}/deploy/wisdom_child_schools_credentials.txt"
    local_txt = ROOT / "deploy" / "wisdom_child_schools_credentials.txt"
    try:
        sftp.get(remote_txt, str(local_txt))
        print("GET", local_txt)
    except OSError as ex:
        print("Could not download credentials file:", ex)

    cmd = r"""
set -e
cd /opt/xander-school/deploy && docker compose -f docker-compose.prod.yml --env-file .env.production restart app
sleep 4
docker exec xander_school_app php -r "opcache_reset(); echo 'opcache ok';"
docker exec xander_school_app grep -n "resetWisdomChildDefaultPasswords" /var/www/html/app/Services/SchoolHierarchyService.php | head -n 3
"""
    _, o2, e2 = c.exec_command(cmd, timeout=180)
    sys.stdout.write(o2.read().decode(errors="replace"))
    err2 = e2.read().decode(errors="replace")
    if err2.strip():
        sys.stderr.write(err2)

    sftp.close()
    c.close()
    return 0 if code == 0 else code


if __name__ == "__main__":
    raise SystemExit(main())
