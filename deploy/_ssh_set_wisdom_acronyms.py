#!/usr/bin/env python3
"""Set Wisdom child-school acronyms to capital letters only."""
from __future__ import annotations

import os
import re
import sys
import time
from pathlib import Path

import paramiko

ROOT = Path(r"C:\xampp7\htdocs\Xander-school")
HOST = "66.29.135.120"
USER = "root"
_src = (Path(__file__).with_name("_ssh_deploy_discipline_codes.py")).read_text(encoding="utf-8")
_m = re.search(r'PASSWORD = os\.environ\.get\("VPS_PASSWORD", "([^"]*)"\)', _src)
PASSWORD = os.environ.get("VPS_PASSWORD", _m.group(1) if _m else "")
REMOTE_APP = "/opt/xander-school/app"


def main() -> int:
    dry = "--execute" not in sys.argv
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(HOST, username=USER, password=PASSWORD, timeout=90, banner_timeout=90)
    sftp = client.open_sftp()
    client.exec_command(f"mkdir -p {REMOTE_APP}/deploy")
    time.sleep(0.2)
    uploads = [
        ("deploy/set_wisdom_child_acronyms.php", f"{REMOTE_APP}/deploy/set_wisdom_child_acronyms.php"),
        ("app/Services/SchoolHierarchyService.php", f"{REMOTE_APP}/app/Services/SchoolHierarchyService.php"),
    ]
    for rel, remote in uploads:
        print("PUT", rel)
        sftp.put(str(ROOT / rel), remote)
    sftp.close()
    flag = " --dry-run" if dry else ""
    cmd = "docker exec xander_school_app php /var/www/html/deploy/set_wisdom_child_acronyms.php" + flag
    print("RUN", cmd)
    _, stdout, stderr = client.exec_command(cmd, timeout=180)
    code = stdout.channel.recv_exit_status()
    out = stdout.read().decode(errors="replace")
    err = stderr.read().decode(errors="replace")
    if out:
        print(out)
    if err:
        print(err, file=sys.stderr)
    if not dry and code == 0:
        _, o2, e2 = client.exec_command(
            "docker exec xander_school_app php -r 'opcache_reset();' || true",
            timeout=60,
        )
        o2.channel.recv_exit_status()
    client.close()
    print("EXIT", code)
    return code


if __name__ == "__main__":
    raise SystemExit(main())
