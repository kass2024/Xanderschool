#!/usr/bin/env python3
"""Deploy the school rules discipline catalog and refresh production codes."""
from __future__ import annotations

import os
import re
import time
from pathlib import Path

import paramiko

ROOT = Path(r"C:\xampp7\htdocs\Xander-school")
HOST = "66.29.135.120"
USER = "root"
_src = (ROOT / "deploy" / "_ssh_deploy_discipline_codes.py").read_text(encoding="utf-8")
_m = re.search(r'PASSWORD = os\.environ\.get\("VPS_PASSWORD", "([^"]*)"\)', _src)
PASSWORD = os.environ.get("VPS_PASSWORD", _m.group(1) if _m else "")
REMOTE_BASE = "/opt/xander-school/app"

FILES = [
    "app/Libraries/WisdomDisciplineCatalog.php",
    "app/Models/DisciplineCodeModel.php",
    "app/Views/pages/partials/discipline_codes_settings.php",
]

VERIFY = [
    ("app/Libraries/WisdomDisciplineCatalog.php", "Buy a new one + 10 marks"),
    ("app/Libraries/WisdomDisciplineCatalog.php", "school rules and regulations"),
    ("app/Models/DisciplineCodeModel.php", "school-rules-doc"),
    ("app/Models/DisciplineCodeModel.php", "deactivateRetiredCatalogRows"),
    ("app/Views/pages/partials/discipline_codes_settings.php", "school rules and regulations"),
]

SMOKE = r"""<?php
require '/var/www/html/app/Config/Paths.php';
$paths = new Config\Paths();
require rtrim($paths->systemDirectory, '/ ') . '/bootstrap.php';

$model = new App\Models\DisciplineCodeModel();
$db = Config\Database::connect();
$schools = $db->table('discipline_codes')->select('school_id')->distinct()->get()->getResultArray();
$ids = [];
foreach ($schools as $row) {
    $sid = (int) ($row['school_id'] ?? 0);
    if ($sid > 0) {
        $ids[$sid] = true;
    }
}
$ids[27] = true;
$model->ensureCatalogUpdates(27);

$live = [];
foreach (App\Libraries\WisdomDisciplineCatalog::categories() as $cat) {
    foreach ($cat['items'] as $item) {
        $live[$cat['key'] . '|' . (int) $item['no']] = true;
    }
}
$keys = ['uniforms','staff','places','classmates','leaving','property','behavior','repay','food'];
ksort($ids);
foreach (array_keys($ids) as $sid) {
    echo "SCHOOL {$sid}\n";
    $rows = $db->table('discipline_codes')->where('school_id', $sid)->get()->getResultArray();
    $activeBy = [];
    $activeTotal = 0;
    $extra = 0;
    foreach ($rows as $r) {
        $k = $r['category_key'] . '|' . (int) $r['code_no'];
        if ((int) $r['active'] !== 1) {
            continue;
        }
        $activeTotal++;
        $ck = (string) $r['category_key'];
        $activeBy[$ck] = ($activeBy[$ck] ?? 0) + 1;
        if (!isset($live[$k])) {
            $extra++;
            echo " EXTRA {$k}\n";
        }
    }
    echo "active_total {$activeTotal} extras {$extra}\n";
    foreach ($keys as $key) {
        echo "  {$key} " . (int) ($activeBy[$key] ?? 0) . "\n";
    }
    $rev = $db->table('discipline_catalog_revision')->where('school_id', $sid)->get()->getRowArray();
    echo "revision " . (string) ($rev['revision'] ?? '') . "\n";
    if ((int) $sid === 27) {
        $u = $db->table('discipline_codes')->where('school_id', 27)->where('category_key', 'uniforms')->where('code_no', 1)->get()->getRowArray();
        if ($u) {
            echo "U1 active={$u['active']} marks={$u['first_marks']}/{$u['second_marks']}/{$u['third_marks']}\n";
            echo "U1 se={$u['first_sanction_en']} | {$u['second_sanction_en']} | {$u['third_sanction_en']}\n";
            echo "U1 en={$u['title_en']}\n";
        } else {
            echo "U1 missing\n";
        }
        $b13 = $db->table('discipline_codes')->where('school_id', 27)->where('category_key', 'behavior')->where('code_no', 13)->get()->getResultArray();
        if (!$b13) {
            echo "B13 none\n";
        }
        foreach ($b13 as $row) {
            echo "B13 id={$row['id']} active={$row['active']} en={$row['title_en']} se={$row['first_sanction_en']}\n";
        }
        foreach (['classmates' => 7, 'behavior' => 11, 'food' => 10] as $ck => $expect) {
            $n = (int) $db->table('discipline_codes')->where('school_id', 27)->where('category_key', $ck)->where('active', 1)->countAllResults();
            echo "{$ck}_active {$n} expect {$expect}\n";
        }
    }
}
echo "SMOKE_OK\n";
"""


def main() -> int:
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(HOST, username=USER, password=PASSWORD, timeout=90, banner_timeout=90)
    sftp = client.open_sftp()
    try:
        for rel in FILES:
            local = ROOT / rel
            remote = f"{REMOTE_BASE}/{rel}"
            print("PUT", rel)
            client.exec_command(f"mkdir -p {remote.rsplit('/', 1)[0]}")
            time.sleep(0.05)
            sftp.put(str(local), remote)
        smoke_remote = f"{REMOTE_BASE}/writable/_smoke_school_rules_catalog.php"
        with sftp.file(smoke_remote, "w") as fh:
            fh.write(SMOKE)
    finally:
        sftp.close()

    cmd = r"""
set -e
cd /opt/xander-school/deploy
docker compose -f docker-compose.prod.yml --env-file .env.production restart app
sleep 6
docker exec xander_school_app php -r 'if (function_exists("opcache_reset")) { opcache_reset(); echo "opcache_reset OK\n"; }'
"""
    for rel, needle in VERIFY:
        cmd += f'docker exec xander_school_app grep -n "{needle}" /var/www/html/{rel} | head -2\n'
    cmd += r"""
if docker exec xander_school_app grep -q "Pay 50,000 RWF" /var/www/html/app/Models/DisciplineCodeModel.php; then
  echo "OLD_CARD_RULE_STILL_PRESENT"
  exit 1
fi
docker exec xander_school_app php /var/www/html/writable/_smoke_school_rules_catalog.php
rm -f /opt/xander-school/app/writable/_smoke_school_rules_catalog.php
echo DONE
"""
    _stdin, stdout, stderr = client.exec_command(cmd, timeout=300)
    out = stdout.read().decode("utf-8", "replace")
    err = stderr.read().decode("utf-8", "replace")
    print(out)
    if err.strip():
        print(err)
    code = stdout.channel.recv_exit_status()
    client.close()
    if code != 0 or "DONE" not in out or "SMOKE_OK" not in out:
        return code or 1
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
