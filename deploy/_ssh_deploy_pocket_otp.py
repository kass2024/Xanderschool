#!/usr/bin/env python3
"""Pocket signup requires an SMS code, then links students already filed on that parent number."""
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
    "app/Config/Routes.php",
    "app/Controllers/PocketApi.php",
    "app/Services/Pocket/PocketWalletService.php",
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
docker exec xander_school_app php -l /var/www/html/app/Config/Routes.php
docker exec xander_school_app php -l /var/www/html/app/Controllers/PocketApi.php
docker exec xander_school_app php -l /var/www/html/app/Services/Pocket/PocketWalletService.php
docker exec xander_school_app grep -n "register-start" /var/www/html/app/Config/Routes.php
docker exec xander_school_app grep -n "function prepareSignup" /var/www/html/app/Services/Pocket/PocketWalletService.php
docker exec xander_school_app grep -n "function registerStart" /var/www/html/app/Controllers/PocketApi.php
docker exec xander_school_app php -r '
require "/var/www/html/app/Config/MenuClearance.php";
if (!\Config\MenuClearance::isChiefAccountantPost(28)) { fwrite(STDERR, "isChiefAccountantPost failed\n"); exit(1); }
foreach (["canViewFamilies", "withoutMarksMenus"] as $m) {
    if (!method_exists("Config\\MenuClearance", $m)) { fwrite(STDERR, "missing $m\n"); exit(1); }
}
echo "methods ok\n";
'
docker exec xander_school_app php -r '
foreach (["/login", "/forget/reset", "/api/pocket/health"] as $path) {
    $ctx = stream_context_create(["http" => ["timeout" => 30, "ignore_errors" => true]]);
    $body = @file_get_contents("http://127.0.0.1".$path, false, $ctx);
    $status = $http_response_header[0] ?? "no-status";
    echo $path, " ", $status, "\n";
    if (strpos($status, " 500") !== false) { exit(1); }
    if ($path === "/api/pocket/health" && strpos((string) $body, "Xander Pocket") === false) {
        fwrite(STDERR, "health body unexpected\n");
        exit(1);
    }
}
'
ENVF=/opt/xander-school/deploy/.env.production
DB=$(grep -E '^DB_DATABASE=' "$ENVF" | head -1 | cut -d= -f2-)
U=$(grep -E '^DB_USERNAME=' "$ENVF" | head -1 | cut -d= -f2-)
P=$(grep -E '^DB_PASSWORD=' "$ENVF" | head -1 | cut -d= -f2-)
echo "before wipe"
docker exec xander_school_mysql mysql -u"$U" -p"$P" "$DB" -N -e "SELECT 'parents', COUNT(*) FROM pocket_parents UNION ALL SELECT 'sessions', COUNT(*) FROM pocket_sessions UNION ALL SELECT 'links', COUNT(*) FROM pocket_links"
docker exec xander_school_mysql mysql -u"$U" -p"$P" "$DB" -e "DELETE FROM pocket_sessions; DELETE FROM pocket_links; DELETE FROM pocket_parents; DELETE FROM pocket_signups;"
echo "after wipe"
docker exec xander_school_app php -r '
$ctx = stream_context_create(["http" => ["timeout" => 30, "ignore_errors" => true]]);
$body = @file_get_contents("http://127.0.0.1/api/pocket/health", false, $ctx);
echo $body, "\n";
'
docker exec xander_school_mysql mysql -u"$U" -p"$P" "$DB" -N -e "SELECT phone, role FROM pocket_parents"
echo DONE
"""
    stdin, stdout, stderr = c.exec_command(cmd, timeout=300)
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
