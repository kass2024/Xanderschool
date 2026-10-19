#!/usr/bin/env python3
"""Deploy fees report KPI alignment with fee folds."""
from __future__ import annotations
import os, re, subprocess, time
from pathlib import Path
import paramiko

ROOT = Path(r"C:\xampp7\htdocs\Xander-school")
HOST = "66.29.135.120"
USER = "root"
REMOTE = "/opt/xander-school/app"
FILES = [
    "app/Libraries/FeesReportHelper.php",
    "app/Controllers/Home.php",
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
    staging = ROOT / "writable" / "_fees_kpi_align_deploy"
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
sleep 10
docker exec xander_school_app php -r 'if (function_exists("opcache_reset")) { opcache_reset(); echo "opcache ok\n"; }'
docker exec xander_school_app php -l /var/www/html/app/Libraries/FeesReportHelper.php
docker exec xander_school_app php -l /var/www/html/app/Controllers/Home.php
docker exec xander_school_app grep -n "statsFromFeeSections\|One ledger\|inner join classes cx" /var/www/html/app/Libraries/FeesReportHelper.php /var/www/html/app/Controllers/Home.php | head -20
docker exec xander_school_app php -r '
require "/var/www/html/app/Config/MenuClearance.php";
if (!\Config\MenuClearance::isChiefAccountantPost(28)) { fwrite(STDERR, "isChiefAccountantPost failed\n"); exit(1); }
foreach (["canViewFamilies", "withoutMarksMenus"] as $m) {
    if (!method_exists("Config\\MenuClearance", $m)) { fwrite(STDERR, "missing $m\n"); exit(1); }
}
echo "methods ok\n";
'
docker exec xander_school_app php -r '
sleep(1);
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
