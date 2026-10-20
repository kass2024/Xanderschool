#!/usr/bin/env python3
"""Per-student fee amounts, then clear budget rows and the requests drawn from them."""
from __future__ import annotations

import os
import re
import time
from pathlib import Path

import paramiko

ROOT = Path(r"C:\xampp7\htdocs\Xander-school")
HOST = "66.29.135.120"
USER = "root"
REMOTE = "/opt/xander-school/app"
FILES = [
    "app/Models/SchoolFeesModel.php",
    "app/Models/ExtraFeesModel.php",
    "app/Libraries/FeesReportHelper.php",
    "app/Libraries/FeeInstallmentService.php",
    "app/Libraries/AttendanceScanService.php",
    "app/Controllers/Home.php",
    "app/Controllers/Api.php",
    "app/Views/pages/school_fees_management.php",
    "app/Views/pages/extra_fees_management.php",
    "deploy/_check_student_fee_and_clear_budgets.php",
]


def password() -> str:
    env = os.environ.get("VPS_PASSWORD", "").strip()
    if env:
        return env
    text = (ROOT / "deploy" / "_ssh_deploy_budget_term_kpi.py").read_text(encoding="utf-8")
    match = re.search(r'VPS_PASSWORD",\s*"([^"]+)"', text)
    if not match:
        raise SystemExit("VPS_PASSWORD is not set")
    return match.group(1)


def main() -> int:
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect(HOST, username=USER, password=password(), timeout=90, banner_timeout=90)
    sftp = c.open_sftp()
    for rel in FILES:
        remote = f"{REMOTE}/{rel}"
        print("PUT", rel)
        c.exec_command(f"mkdir -p {remote.rsplit('/', 1)[0]}")
        time.sleep(0.05)
        sftp.put(str(ROOT / rel), remote)
    sftp.close()

    cmd = r"""
set -e
cd /opt/xander-school/deploy
docker compose -f docker-compose.prod.yml --env-file .env.production restart app
sleep 8
docker exec xander_school_app php -r 'if (function_exists("opcache_reset")) { opcache_reset(); echo "opcache ok\n"; }'
docker exec xander_school_app php -l /var/www/html/app/Models/SchoolFeesModel.php
docker exec xander_school_app php -l /var/www/html/app/Models/ExtraFeesModel.php
docker exec xander_school_app php -l /var/www/html/app/Controllers/Home.php
docker exec xander_school_app php -l /var/www/html/app/Views/pages/school_fees_management.php
docker exec xander_school_app php -l /var/www/html/app/Views/pages/extra_fees_management.php
docker exec xander_school_app grep -q "One student" /var/www/html/app/Views/pages/school_fees_management.php
docker exec xander_school_app grep -q "save_extra_fee_student_class" /var/www/html/app/Controllers/Home.php
docker exec xander_school_app grep -q "school_fee_student_amounts" /var/www/html/app/Models/SchoolFeesModel.php
echo FILES_OK
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
docker exec xander_school_app php /var/www/html/deploy/_check_student_fee_and_clear_budgets.php --clear-budgets
echo DONE
"""
    _, stdout, stderr = c.exec_command(cmd, timeout=240)
    out = stdout.read().decode("utf-8", "replace")
    err = stderr.read().decode("utf-8", "replace")
    print(out)
    if err.strip():
        print("STDERR:")
        print(err)
    c.close()
    if "DONE" not in out or "budgets cleared" not in out:
        raise SystemExit("Deploy did not finish cleanly")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
