#!/usr/bin/env python3
"""Deploy the Wisdom attendance workbook and rebuild today's file."""
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
REMOTE_APP = "/opt/xander-school/app"
FILES = ["app/Libraries/WisdomPopulationReport.php"]
LOCAL_XLSX = Path(r"C:\Users\user\Downloads\Wisdom_attendance_2026-10-08.xlsx")

SMOKE = r"""<?php
require '/var/www/html/app/Config/Paths.php';
$paths = new Config\Paths();
require rtrim($paths->systemDirectory, '/ ') . '/bootstrap.php';
$db = Config\Database::connect();
$row = $db->query("SELECT s.id, s.name, at.academic_year AS y
	FROM schools s
	LEFT JOIN active_term at ON at.id = s.active_term
	WHERE IFNULL(s.is_master,0) = 1 AND UPPER(s.name) LIKE '%WISDOM%' AND UPPER(s.name) LIKE '%MUSANZE%'
	LIMIT 1")->getRowArray();
if (!$row) {
	fwrite(STDERR, "NO_MASTER\n");
	exit(1);
}
$id = (int) $row['id'];
$year = (int) ($row['y'] ?? 0);
$date = date('Y-m-d');
echo 'master=' . $id . ' ' . $row['name'] . ' year=' . $year . ' date=' . $date . PHP_EOL;
$m = new ReflectionMethod(App\Libraries\WisdomPopulationReport::class, 'collect');
$m->setAccessible(true);
$data = $m->invoke(null, $id, $year, $date);
$badHs = 0;
foreach ($data['schools'] as $school) {
	$bands = [];
	foreach ($school['bands'] as $band => $classes) {
		if ($classes === []) {
			continue;
		}
		$names = [];
		foreach ($classes as $class) {
			$names[] = $class['label'] . '=' . (int) $class['enrolled'] . '/p' . (int) $class['present'];
		}
		$bands[] = $band . '[' . implode(', ', $names) . ']';
	}
	$short = $school['short'];
	$isMaster = !empty($school['is_master']);
	$hasHs = ($school['bands']['high_school'] ?? []) !== [] || ($school['bands']['tvet'] ?? []) !== [];
	if (!$isMaster && $hasHs) {
		$badHs++;
		echo "BAD_HS\t";
	}
	$postBits = [];
	foreach ($school['posts'] as $post => $people) {
		$in = 0;
		foreach ($people as $person) {
			$in += (int) ($person['present'] ?? 0);
		}
		$postBits[] = $post . '=' . count($people) . '/p' . $in;
	}
	echo $short
		. "\tenrolled=" . (int) $school['expected']
		. "\tF=" . (int) $school['expected_f']
		. "\tM=" . (int) $school['expected_m']
		. "\tpresent=" . (int) $school['present']
		. "\tteachers=" . (int) $school['teachers']['present'] . '/' . (int) $school['teachers']['missing']
		. "\tsupport=" . (int) $school['support']['present'] . '/' . (int) $school['support']['missing']
		. "\tPOSTS " . implode(', ', $postBits)
		. "\t" . implode(' | ', $bands) . PHP_EOL;
}
$t = $data['totals'];
echo 'TOTAL enrolled=' . (int) $t['expected'] . ' F=' . (int) $t['expected_f'] . ' M=' . (int) $t['expected_m'] . ' present=' . (int) $t['present'] . PHP_EOL;
if ($badHs > 0) {
	fwrite(STDERR, "CHILD_HAS_HIGH_SCHOOL\n");
	exit(1);
}
$book = App\Libraries\WisdomPopulationReport::build($id, $year, $date);
$writer = new PhpOffice\PhpSpreadsheet\Writer\Xlsx($book);
$path = '/var/www/html/writable/Wisdom_attendance_' . $date . '.xlsx';
$writer->save($path);
echo 'SAVED ' . $path . PHP_EOL;
echo "SMOKE_OK\n";
"""


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
    smoke_remote = f"{REMOTE_APP}/writable/_smoke_attendance_workbook.php"
    with sftp.file(smoke_remote, "w") as fh:
        fh.write(SMOKE)
    sftp.close()
    cmd = r"""
set -e
cd /opt/xander-school/deploy
docker compose -f docker-compose.prod.yml --env-file .env.production restart app
sleep 8
docker exec xander_school_app php -r 'if (function_exists("opcache_reset")) { opcache_reset(); echo "opcache ok\n"; }'
docker exec xander_school_app php -l /var/www/html/app/Libraries/WisdomPopulationReport.php
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
docker exec xander_school_app grep -n "classBand" /var/www/html/app/Libraries/WisdomPopulationReport.php | head -2
docker exec xander_school_app php /var/www/html/writable/_smoke_attendance_workbook.php
rm -f /opt/xander-school/app/writable/_smoke_attendance_workbook.php
echo DONE
"""
    _, o, e = c.exec_command(cmd, timeout=300)
    out = o.read().decode("utf-8", "replace")
    err = e.read().decode("utf-8", "replace")
    print(out)
    if err.strip():
        print("STDERR", err)
    code = o.channel.recv_exit_status()
    saved = re.search(r"SAVED (/var/www/html/writable/Wisdom_attendance_\d{4}-\d{2}-\d{2}\.xlsx)", out)
    if code == 0 and "SMOKE_OK" in out and saved:
        remote_xlsx = saved.group(1).replace("/var/www/html", REMOTE_APP)
        sftp = c.open_sftp()
        sftp.get(remote_xlsx, str(LOCAL_XLSX))
        sftp.remove(remote_xlsx)
        sftp.close()
        print("DOWNLOADED", LOCAL_XLSX)
    c.close()
    return code


if __name__ == "__main__":
    raise SystemExit(main())
