#!/usr/bin/env python3
"""Deploy nursery and primary reports: one A4 portrait page, short remarks."""
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
    "app/Views/pages/reports/wisdom_secondary_report.php",
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
docker exec xander_school_app php -l /var/www/html/app/Libraries/ReportRemarks.php
docker exec xander_school_app php -l /var/www/html/app/Controllers/Home.php
docker exec xander_school_app php -l /var/www/html/app/Helpers/qonics_helper.php
docker exec xander_school_app php -l /var/www/html/app/Views/pages/reports/wisdom_secondary_progress.php
docker exec xander_school_app php -l /var/www/html/app/Views/pages/reports/wisdom_secondary_report.php
docker exec xander_school_app grep -n "PERIODIC REPORT" /var/www/html/app/Views/pages/reports/wisdom_secondary_report.php | head -1
if docker exec xander_school_app grep -q "SCORES ADJUSTED" /var/www/html/app/Views/pages/reports/wisdom_secondary_report.php; then
  echo "SCORES ADJUSTED still present"
  exit 1
fi
docker exec xander_school_app php -l /var/www/html/app/Views/pages/reports/wisdom_primary_report.php
docker exec xander_school_app php -l /var/www/html/app/Views/pages/reports/wisdom_nursery_report.php
docker exec xander_school_app grep -n "REPORT_PAGE" /var/www/html/app/Controllers/Home.php | head -1
docker exec xander_school_app grep -n "sort_report_cards_by_position" /var/www/html/app/Views/pages/reports/wisdom_secondary_progress.php | head -1
docker exec xander_school_app grep -n "wsp-conduct" /var/www/html/app/Views/pages/reports/wisdom_secondary_progress.php | head -1
docker exec xander_school_app grep -n "PROGRESSIVE SCHOOL REPORT" /var/www/html/app/Views/pages/reports/wisdom_secondary_progress.php | head -1
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
docker exec xander_school_app grep -n "Primary — grade and comment" /var/www/html/app/Views/pages/school_settings.php | head -1
docker exec xander_school_app grep -n "gradeLetterFor" /var/www/html/app/Views/pages/reports/wisdom_primary_report.php | head -1
ENVF=/opt/xander-school/deploy/.env.production
DB=$(grep -E '^DB_DATABASE=' "$ENVF" | head -1 | cut -d= -f2-)
U=$(grep -E '^DB_USERNAME=' "$ENVF" | head -1 | cut -d= -f2-)
P=$(grep -E '^DB_PASSWORD=' "$ENVF" | head -1 | cut -d= -f2-)
HAS=$(docker exec xander_school_mysql mysql -u"$U" -p"$P" "$DB" -N -e "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='grade' AND COLUMN_NAME='grade_letter'")
if [ "$HAS" = "0" ]; then
  docker exec xander_school_mysql mysql -u"$U" -p"$P" "$DB" -e "ALTER TABLE grade ADD COLUMN grade_letter VARCHAR(8) NULL DEFAULT NULL AFTER faculty_id"
  echo COLUMN_ADDED
else
  echo COLUMN_EXIST
fi
docker exec xander_school_mysql mysql -u"$U" -p"$P" "$DB" -e "
INSERT INTO grade (faculty_id, school_id, grade_letter, color_title, max_point, min_point, color, created_by, created_at, updated_at)
SELECT 3, s.id, b.letter, b.comment, b.max_point, b.min_point, b.color, 0, NOW(), NOW()
FROM schools s
JOIN (
  SELECT 'A' letter, 'Excellent' comment, 100 max_point, 90 min_point, '#16a34a' color
  UNION ALL SELECT 'B', 'Very good', 89, 80, '#22c55e'
  UNION ALL SELECT 'C', 'Good', 79, 70, '#84cc16'
  UNION ALL SELECT 'D', 'Fair', 69, 60, '#eab308'
  UNION ALL SELECT 'E', 'Pass', 59, 50, '#f97316'
  UNION ALL SELECT 'F', 'Fail', 49, 0, '#dc2626'
) b
WHERE NOT EXISTS (SELECT 1 FROM grade g WHERE g.school_id = s.id AND g.faculty_id = 3);
"
docker exec xander_school_mysql mysql -u"$U" -p"$P" "$DB" -N -e "SELECT school_id, grade_letter, color_title, min_point, max_point FROM grade WHERE faculty_id=3 AND school_id=27 ORDER BY max_point DESC"
echo DONE
"""
    _, o, e = c.exec_command(cmd, timeout=240)
    print(o.read().decode("utf-8", "replace"))
    err = e.read().decode("utf-8", "replace")
    if err.strip():
        print("STDERR", err)
    code = o.channel.recv_exit_status()
    c.close()
    return code


if __name__ == "__main__":
    raise SystemExit(main())
