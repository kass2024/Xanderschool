#!/usr/bin/env python3
"""Staff profile: edit name, email, phone, address, and photo."""
from __future__ import annotations

import re
from pathlib import Path

import paramiko

ROOT = Path(r"C:\xampp7\htdocs\Xander-school")
HOST = "66.29.135.120"
USER = "root"
_src = (ROOT / "deploy" / "_ssh_deploy_discipline_codes.py").read_text(encoding="utf-8")
_m = re.search(r'PASSWORD = os\.environ\.get\("VPS_PASSWORD", "([^"]*)"\)', _src)
PASSWORD = _m.group(1) if _m else ""
REMOTE_APP = "/opt/xander-school/app"
FILES = [
    "app/Controllers/Home.php",
    "app/Views/pages/staff.php",
    "app/Views/pages/partials/country_select_staff.php",
    "app/Views/pages/partials/mdl_staff.php",
    "app/Views/main.php",
    "app/Views/main2.php",
]


def main() -> int:
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect(HOST, username=USER, password=PASSWORD, timeout=120, banner_timeout=120, auth_timeout=120)
    sftp = c.open_sftp()
    for rel in FILES:
        local = ROOT / rel
        remote = f"{REMOTE_APP}/{rel}"
        print("PUT", rel, local.stat().st_size)
        sftp.put(str(local), remote)
    sftp.close()
    cmd = r"""
set -e
cd /opt/xander-school/deploy
docker compose -f docker-compose.prod.yml --env-file .env.production restart app
sleep 8
docker exec xander_school_app php -r 'if (function_exists("opcache_reset")) { opcache_reset(); echo "opcache ok\n"; }'
docker exec xander_school_app php -l /var/www/html/app/Controllers/Home.php
docker exec xander_school_app php -l /var/www/html/app/Views/pages/staff.php
docker exec xander_school_app grep -n "function save_staff_profile" /var/www/html/app/Controllers/Home.php
docker exec xander_school_app grep -n "id=\"sp-email\"" /var/www/html/app/Views/pages/staff.php
docker exec xander_school_app grep -n "Click or drop a photo" /var/www/html/app/Views/pages/staff.php
docker exec xander_school_app php -r '
require "/var/www/html/app/Config/MenuClearance.php";
if (!\Config\MenuClearance::isChiefAccountantPost(28)) { fwrite(STDERR, "isChiefAccountantPost failed\n"); exit(1); }
foreach (["canViewFamilies", "withoutMarksMenus"] as $m) {
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
"""
    stdin, stdout, stderr = c.exec_command(cmd, timeout=240)
    out = stdout.read().decode("utf-8", "replace")
    err = stderr.read().decode("utf-8", "replace")
    code = stdout.channel.recv_exit_status()
    print(out)
    if err.strip():
        print("STDERR:", err)
    c.close()
    print("exit", code)
    return code


if __name__ == "__main__":
    raise SystemExit(main())
