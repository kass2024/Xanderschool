#!/usr/bin/env python3
"""Alice Namahoro at Musanze: settings, pocket money, canteen, and prep menus."""
from __future__ import annotations

import importlib.util
import sys
from pathlib import Path

import paramiko

ROOT = Path(r"C:\xampp7\htdocs\Xander-school")
spec = importlib.util.spec_from_file_location("dep", ROOT / "deploy" / "_ssh_deploy_day_scholar_taps.py")
dep = importlib.util.module_from_spec(spec)
spec.loader.exec_module(dep)
HOST = dep.HOST
USER = dep.USER
PASSWORD = dep.PASSWORD
REMOTE = "/opt/xander-school/app"
FILES = [
    "app/Config/MenuClearance.php",
    "app/Helpers/qonics_helper.php",
    "app/Views/pages/school_settings.php",
]


def main() -> int:
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
docker exec xander_school_app php -l /var/www/html/app/Config/MenuClearance.php
docker exec xander_school_app php -l /var/www/html/app/Helpers/qonics_helper.php
docker exec xander_school_app php -l /var/www/html/app/Views/pages/school_settings.php
docker exec xander_school_app grep -n "isAliceNamahoroAtMusanze" /var/www/html/app/Views/pages/school_settings.php
docker exec xander_school_app grep -n "function isAliceNamahoroAtMusanze" /var/www/html/app/Config/MenuClearance.php
docker exec xander_school_app grep -n "isAliceNamahoroAtMusanze" /var/www/html/app/Helpers/qonics_helper.php
docker exec xander_school_app php -r '
require "/var/www/html/app/Config/MenuClearance.php";
if (!\Config\MenuClearance::isChiefAccountantPost(28)) { fwrite(STDERR, "isChiefAccountantPost failed\n"); exit(1); }
foreach (["canViewFamilies", "withoutMarksMenus", "isAliceNamahoroAtMusanze", "canUsePocketMoney"] as $m) {
    if (!method_exists("Config\\MenuClearance", $m)) { fwrite(STDERR, "missing $m\n"); exit(1); }
}
echo "methods ok\n";
'
docker exec xander_school_app php -r '
foreach (["/login", "/forget/reset"] as $path) {
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


if __name__ == "__main__":
    raise SystemExit(main())
