#!/usr/bin/env python3
"""Deploy card_uid fix and smoke-test lookup on VPS."""
from __future__ import annotations
import os, sys, time
from pathlib import Path
import paramiko

ROOT = Path(r"C:\xampp7\htdocs\Xander-school")
HOST = "66.29.135.120"
USER = "root"
PASSWORD = os.environ.get("VPS_PASSWORD", "6W7sa2g4dMEwcN80ZU")
REMOTE_BASE = "/opt/xander-school/app"

FILES = [
    "app/Helpers/card_uid_helper.php",
    "app/Models/StudentVisitorModel.php",
    "app/Controllers/Home.php",
    "app/Controllers/Api.php",
    "app/Views/pages/parent_visiting/assign.php",
    "app/Views/pages/parent_visiting/verify.php",
    "app/Views/pages/students/assign_card.php",
    "public/assets/js/card-uid.js",
]

PHP_TEST = r'''<?php
require '/var/www/html/app/Helpers/card_uid_helper.php';
$tests = ['CD77046C', '46C077CD', '8E75AF57'];
foreach ($tests as $t) {
    $v = card_uid_lookup_variants($t);
    $n = normalize_card_uid($t);
    echo "$t => norm=$n variants=" . implode(',', $v) . "\n";
}
echo "OK\n";
'''


def main():
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect(HOST, username=USER, password=PASSWORD, timeout=90)
    sftp = c.open_sftp()
    for rel in FILES:
        local = ROOT / rel
        remote = f"{REMOTE_BASE}/{rel}".replace("\\", "/")
        print("PUT", rel)
        sftp.put(str(local), remote)
    sftp.close()

    cmds = [
        "docker exec xander_school_app php -l /var/www/html/app/Helpers/card_uid_helper.php",
        f"docker exec xander_school_app php -r \"{PHP_TEST.replace(chr(10), ' ')}\"",
        "cd /opt/xander-school/deploy && docker compose -f docker-compose.prod.yml --env-file .env.production restart app",
        "sleep 2",
        "docker exec xander_school_app php -r 'opcache_reset();' || true",
    ]
    for cmd in cmds:
        print("\n$", cmd[:120])
        _, o, e = c.exec_command(cmd, timeout=120)
        print(o.read().decode())
        err = e.read().decode()
        if err.strip():
            print("STDERR:", err)
    c.close()
    print("DONE")


if __name__ == "__main__":
    main()
