#!/usr/bin/env python3
"""Deploy budget KPI layout so large RWF figures stay inside the cards."""
from __future__ import annotations
import os, time
from pathlib import Path
import paramiko

ROOT = Path(r"C:\xampp7\htdocs\Xander-school")
HOST = "66.29.135.120"
USER = "root"
PASSWORD = os.environ.get("VPS_PASSWORD", "6W7sa2g4dMEwcN80ZU")
REMOTE = "/opt/xander-school/app"
FILES = [
    "app/Views/pages/budget/prepare.php",
    "app/Views/pages/budget/edit_budget.php",
    "app/Views/pages/budget/dashboard.php",
    "public/assets/css/budget-preparation.css",
]

def main():
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect(HOST, username=USER, password=PASSWORD, timeout=60)
    c.get_transport().set_keepalive(15)
    sftp = c.open_sftp()
    for rel in FILES:
        parent = str(Path(rel).parent).replace("\\", "/")
        c.exec_command(f"mkdir -p {REMOTE}/{parent}")
        time.sleep(0.05)
        remote = f"{REMOTE}/{rel}"
        tmp = remote + ".uploading"
        print("PUT", rel)
        sftp.put(str(ROOT / rel), tmp)
        sftp.posix_rename(tmp, remote)
    sftp.close()
    cmd = r"""
cd /opt/xander-school/deploy
docker compose -f docker-compose.prod.yml --env-file .env.production restart app
sleep 8
docker exec xander_school_app php -r 'if (function_exists("opcache_reset")) { opcache_reset(); echo "opcache reset\n"; }'
docker exec xander_school_app php -l /var/www/html/app/Views/pages/budget/prepare.php
docker exec xander_school_app php -l /var/www/html/app/Views/pages/budget/edit_budget.php
docker exec xander_school_app php -l /var/www/html/app/Views/pages/budget/dashboard.php
docker exec xander_school_app php -r '
require "/var/www/html/app/Config/MenuClearance.php";
if (!\Config\MenuClearance::isChiefAccountantPost(28)) { fwrite(STDERR, "isChiefAccountantPost missing\n"); exit(1); }
if (!method_exists(\Config\MenuClearance::class, "canViewFamilies")) { fwrite(STDERR, "canViewFamilies missing\n"); exit(1); }
if (!method_exists(\Config\MenuClearance::class, "withoutMarksMenus")) { fwrite(STDERR, "withoutMarksMenus missing\n"); exit(1); }
echo "menu ok\n";
'
docker exec xander_school_app grep -n "bp-year-figures\|container-name: termcard" /var/www/html/app/Views/pages/budget/prepare.php /var/www/html/public/assets/css/budget-preparation.css | head -20
docker exec xander_school_app php -r '
foreach (["http://127.0.0.1/login", "http://127.0.0.1/forget/reset"] as $u) {
  $h = @get_headers($u);
  $line = $h[0] ?? "no-status";
  echo $u, " ", $line, "\n";
  if (strpos($line, " 500") !== false) { fwrite(STDERR, "500 on $u\n"); exit(1); }
}
'
echo DONE
"""
    stdin, stdout, stderr = c.exec_command(cmd, timeout=180)
    print(stdout.read().decode("utf-8", "replace"))
    err = stderr.read().decode("utf-8", "replace")
    if err.strip():
        print("STDERR:", err)
    code = stdout.channel.recv_exit_status()
    c.close()
    if code != 0:
        raise SystemExit(code)
    print("Deploy complete.")

if __name__ == "__main__":
    main()
