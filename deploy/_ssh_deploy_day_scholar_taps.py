#!/usr/bin/env python3
"""Deploy day-scholar morning, lunch, return, and evening dashboard taps."""
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
REMOTE = "/opt/xander-school/app"
FILES = [
    "app/Controllers/DayScholar.php",
    "app/Config/Routes.php",
    "app/Services/WisdomGroupOverview.php",
    "app/Views/pages/dashboard.php",
    "app/Views/pages/wisdom_day_scholar.php",
]


def _deploy_once() -> int:
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(HOST, username=USER, password=PASSWORD, timeout=120, banner_timeout=120, auth_timeout=120)
    sftp = client.open_sftp()
    try:
        for rel in FILES:
            local = ROOT / rel
            remote = f"{REMOTE}/{rel}"
            print("PUT", rel, local.stat().st_size)
            sftp.put(str(local), remote)
    finally:
        sftp.close()

    checks = r"""
set -e
cd /opt/xander-school/deploy
docker compose -f docker-compose.prod.yml --env-file .env.production restart app
sleep 8
docker exec xander_school_app php -r 'if (function_exists("opcache_reset")) { opcache_reset(); echo "opcache_reset OK\n"; }'
docker exec xander_school_app php -l /var/www/html/app/Controllers/DayScholar.php
docker exec xander_school_app php -l /var/www/html/app/Services/WisdomGroupOverview.php
docker exec xander_school_app php -l /var/www/html/app/Views/pages/dashboard.php
docker exec xander_school_app php -l /var/www/html/app/Views/pages/wisdom_day_scholar.php
docker exec xander_school_app php -l /var/www/html/app/Config/Routes.php
docker exec xander_school_app grep -n "Day scholars" /var/www/html/app/Views/pages/dashboard.php
docker exec xander_school_app grep -n "function dayScholarTapSummary" /var/www/html/app/Services/WisdomGroupOverview.php
docker exec xander_school_app grep -n "DayScholar::today" /var/www/html/app/Config/Routes.php
docker exec xander_school_app php -r '
require "/var/www/html/app/Config/MenuClearance.php";
if (!\Config\MenuClearance::isChiefAccountantPost(28)) { fwrite(STDERR, "isChiefAccountantPost failed\n"); exit(1); }
foreach (["canViewFamilies", "withoutMarksMenus"] as $m) {
    if (!method_exists("Config\\MenuClearance", $m)) { fwrite(STDERR, "missing $m\n"); exit(1); }
}
echo "methods ok\n";
'
docker exec xander_school_app php -r '
foreach (["/login", "/forget/reset", "/wisdom-day-scholar/1/morning"] as $path) {
    $ctx = stream_context_create(["http" => ["timeout" => 20, "ignore_errors" => true]]);
    @file_get_contents("http://127.0.0.1".$path, false, $ctx);
    $status = $http_response_header[0] ?? "no-status";
    echo $path, " ", $status, "\n";
    if (strpos($status, " 500") !== false) { exit(1); }
}
'
echo DONE
"""
    _stdin, stdout, stderr = client.exec_command(checks, timeout=240)
    sys.stdout.buffer.write(stdout.read())
    sys.stderr.buffer.write(stderr.read())
    code = stdout.channel.recv_exit_status()
    client.close()
    print("EXIT", code)
    return code


def main() -> int:
    last_err: Exception | None = None
    for attempt in range(1, 6):
        try:
            print(f"attempt {attempt}")
            return _deploy_once()
        except Exception as ex:  # noqa: BLE001
            last_err = ex
            print("fail", type(ex).__name__, ex)
            time.sleep(8)
    print("ALL FAILED", last_err)
    return 1


if __name__ == "__main__":
    raise SystemExit(main())
