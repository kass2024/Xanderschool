#!/usr/bin/env python3
"""Restore the Wisdom master director dashboard and verify campus totals."""
from __future__ import annotations

import re
import time
from pathlib import Path

import paramiko

ROOT = Path(r"C:\xampp7\htdocs\Xander-school")
HOST = "66.29.135.120"
USER = "root"
_src = (ROOT / "deploy" / "_ssh_deploy_discipline_codes.py").read_text(encoding="utf-8")
_m = re.search(r'PASSWORD = os\.environ\.get\("VPS_PASSWORD", "([^"]*)"\)', _src)
PASSWORD = _m.group(1) if _m else ""
REMOTE_BASE = "/opt/xander-school/app"
FILES = [
    "app/Controllers/Home.php",
    "app/Config/Routes.php",
]
VERIFY = [
    ("app/Controllers/Home.php", "see every child campus"),
    ("app/Config/Routes.php", "wisdom-staff-today"),
    ("app/Libraries/CardRegistry.php", "Staff cards stay inside one school"),
]
SMOKE = r"""<?php
require '/var/www/html/app/Config/Paths.php';
$paths = new Config\Paths();
require rtrim($paths->systemDirectory, '/ ') . '/bootstrap.php';
$o = new App\Services\WisdomGroupOverview();
echo 'leader=' . ($o->showGroupDashboard(27, 29, 27) ? 'yes' : 'no') . PHP_EOL;
echo 'not_leader=' . ($o->showGroupDashboard(27, 1, 27) ? 'yes' : 'no') . PHP_EOL;
echo 'child_blocked=' . ($o->showGroupDashboard(41, 29, 41) ? 'yes' : 'no') . PHP_EOL;
$db = Config\Database::connect();
$row = $db->query('SELECT at.academic_year AS y FROM schools s JOIN active_term at ON at.id = s.active_term WHERE s.id = 27')->getRowArray();
$year = (int) ($row['y'] ?? 0);
echo 'year=' . $year . PHP_EOL;
$s = $o->summary(27, $year);
echo 'schools=' . count($s['schools']) . ' students=' . (int) $s['totals']['students'] . ' staff=' . (int) $s['totals']['staff'] . PHP_EOL;
foreach ($s['schools'] as $campus) {
    echo (int) $campus['id'] . "\t" . $campus['name'] . "\tstudents=" . (int) $campus['students'] . "\tstaff=" . (int) $campus['staff'] . PHP_EOL;
}
echo "SMOKE_OK\n";
"""


def main() -> int:
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect(HOST, username=USER, password=PASSWORD, timeout=90, banner_timeout=90)
    sftp = c.open_sftp()
    for rel in FILES:
        local = ROOT / rel
        remote = f"{REMOTE_BASE}/{rel}"
        print("PUT", rel)
        c.exec_command(f"mkdir -p {remote.rsplit('/', 1)[0]}")
        time.sleep(0.05)
        sftp.put(str(local), remote)
    smoke_remote = f"{REMOTE_BASE}/writable/_smoke_director_dashboard.php"
    with sftp.file(smoke_remote, "w") as fh:
        fh.write(SMOKE)
    sftp.close()
    cmd = r"""
set -e
cd /opt/xander-school/deploy
docker compose -f docker-compose.prod.yml --env-file .env.production restart app
sleep 6
docker exec xander_school_app php -r 'opcache_reset();'
"""
    for rel, needle in VERIFY:
        cmd += f'docker exec xander_school_app grep -n "{needle}" /var/www/html/{rel} | head -2\n'
    cmd += r"""
docker exec xander_school_app php /var/www/html/writable/_smoke_director_dashboard.php
rm -f /opt/xander-school/app/writable/_smoke_director_dashboard.php
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
