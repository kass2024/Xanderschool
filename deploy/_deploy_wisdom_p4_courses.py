#!/usr/bin/env python3
"""Upload and run Wisdom P4 REB courses seed on VPS."""
from __future__ import annotations

import sys
import time
from pathlib import Path

import paramiko

ROOT = Path(r"C:\xampp7\htdocs\Xander-school")
HOST = "66.29.135.120"
USER = "root"
PASSWORD = "6W7sa2g4dMEwcN80ZU"
REMOTE = "/opt/xander-school/app/deploy/seed_wisdom_p4_courses.php"
LOCAL = ROOT / "deploy/seed_wisdom_p4_courses.php"


def main() -> int:
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(HOST, username=USER, password=PASSWORD, timeout=60)

    sftp = client.open_sftp()
    client.exec_command("mkdir -p /opt/xander-school/app/deploy")
    time.sleep(0.3)
    sftp.put(str(LOCAL), REMOTE)
    sftp.close()
    print("Uploaded seed script")

    cmd = "docker exec xander_school_app php /var/www/html/deploy/seed_wisdom_p4_courses.php"
    _, stdout, stderr = client.exec_command(cmd, timeout=120)
    code = stdout.channel.recv_exit_status()
    out = stdout.read().decode(errors="replace")
    err = stderr.read().decode(errors="replace")
    if out:
        print(out)
    if err:
        print(err, file=sys.stderr)

    print("Restarting app container...")
    _, stdout2, _ = client.exec_command(
        "cd /opt/xander-school/deploy && docker compose -f docker-compose.prod.yml --env-file .env.production restart app",
        timeout=120,
    )
    stdout2.channel.recv_exit_status()
    print(stdout2.read().decode(errors="replace"))

    print("Resetting opcache...")
    _, stdout3, _ = client.exec_command(
        'docker exec xander_school_app php -r "if (function_exists(\'opcache_reset\')) { opcache_reset(); echo \'opcache_reset ok\'; }"',
        timeout=30,
    )
    stdout3.channel.recv_exit_status()
    print(stdout3.read().decode(errors="replace"))

    client.close()
    print("EXIT", code)
    return code


if __name__ == "__main__":
    raise SystemExit(main())
