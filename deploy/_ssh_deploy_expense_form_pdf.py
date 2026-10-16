#!/usr/bin/env python3
"""Deploy clearer expense item entry and the approved-form filing PDF."""
from __future__ import annotations
import os, re, subprocess, time
from pathlib import Path
import paramiko

ROOT = Path(r"C:\xampp7\htdocs\Xander-school")
HOST = "66.29.135.120"
USER = "root"
REMOTE = "/opt/xander-school/app"
FILES = [
    "app/Views/pages/budget/cash_request_form.php",
    "app/Views/pages/budget/cash_request_view.php",
    "app/Views/pages/budget/cash_request_filing_pdf.php",
    "app/Services/Budget/CashRequestFilingPdf.php",
    "app/Controllers/BudgetCashflow.php",
    "app/Config/Routes.php",
    "public/assets/css/budget-preparation.css",
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


def main():
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect(HOST, username=USER, password=password(), timeout=60)
    staging = ROOT / "writable" / "_erf_pdf_deploy"
    staging.mkdir(parents=True, exist_ok=True)
    sftp = c.open_sftp()
    for rel in FILES:
        print("PUT", rel)
        parent = str(Path(rel).parent).replace("\\", "/")
        c.exec_command(f"mkdir -p {REMOTE}/{parent}")
        time.sleep(0.05)
        local = staging / rel.replace("/", "__")
        local.write_bytes(subprocess.check_output(["git", "show", f"HEAD:{rel}"], cwd=str(ROOT)))
        sftp.put(str(local), f"{REMOTE}/{rel}")
    sftp.close()
    cmd = r"""
cd /opt/xander-school/deploy
docker compose -f docker-compose.prod.yml --env-file .env.production restart app
sleep 8
docker exec xander_school_app php -r 'if (function_exists("opcache_reset")) { opcache_reset(); echo "opcache ok\n"; }'
docker exec xander_school_app php -l /var/www/html/app/Controllers/BudgetCashflow.php
docker exec xander_school_app php -l /var/www/html/app/Services/Budget/CashRequestFilingPdf.php
docker exec xander_school_app php -l /var/www/html/app/Views/pages/budget/cash_request_form.php
docker exec xander_school_app php -l /var/www/html/app/Config/Routes.php
docker exec xander_school_app grep -n "Type the amount" /var/www/html/app/Views/pages/budget/cash_request_form.php | head -1
docker exec xander_school_app grep -n "cash_request_pdf" /var/www/html/app/Config/Routes.php | head -1
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
echo DONE
"""
    _, stdout, stderr = c.exec_command(cmd, timeout=180)
    print(stdout.read().decode("utf-8", "replace"))
    err = stderr.read().decode("utf-8", "replace")
    if err.strip():
        print("STDERR:", err)
    c.close()
    print("Deploy complete.")


if __name__ == "__main__":
    main()
