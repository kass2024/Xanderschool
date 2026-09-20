#!/usr/bin/env python3
"""Deploy staff clock-in/out PDF (mPDF, no KPI cards, no wkhtmltopdf)."""
from __future__ import annotations

import os
import sys
import tarfile
import tempfile
from pathlib import Path

import paramiko

ROOT = Path(r"C:\xampp7\htdocs\Xander-school")
HOST = "66.29.135.120"
USER = "root"
PASSWORD = os.environ.get("VPS_PASSWORD", "6W7sa2g4dMEwcN80ZU")
REMOTE_APP = "/opt/xander-school/app"

FILES = [
    "app/Controllers/Home.php",
    "app/Libraries/MpdfReport.php",
    "app/Views/pages/reports/staff_report_individual.php",
    "app/Views/pages/reports/staff_report_clock_pdf.php",
    "composer.json",
    "composer.lock",
    "deploy/docker-entrypoint.sh",
]

VENDOR_DIRS = [
    "mpdf",
    "setasign",
    "paragonie/random_compat",
    "myclabs/deep-copy",
]


def put_file(sftp, rel: str) -> None:
    local = ROOT / rel
    remote = f"{REMOTE_APP}/{rel.replace(chr(92), '/')}"
    remote_dir = remote.rsplit("/", 1)[0]
    try:
        sftp.stat(remote_dir)
    except FileNotFoundError:
        parts = remote_dir.split("/")
        cur = ""
        for p in parts:
            if p == "":
                continue
            cur += "/" + p
            try:
                sftp.stat(cur)
            except FileNotFoundError:
                sftp.mkdir(cur)
    sftp.put(str(local), remote)
    print("PUT", rel)


def main() -> int:
    tgz_path = Path(tempfile.gettempdir()) / "xander_mpdf_vendor.tgz"
    with tarfile.open(tgz_path, "w:gz") as tar:
        for d in VENDOR_DIRS:
            src = ROOT / "vendor" / d
            tar.add(src, arcname=d)
            print("TAR", d)
    print("TGZ", tgz_path, tgz_path.stat().st_size)

    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect(HOST, username=USER, password=PASSWORD, timeout=180, banner_timeout=180, auth_timeout=180)
    sftp = c.open_sftp()
    for rel in FILES:
        put_file(sftp, rel)
    sftp.put(str(tgz_path), "/tmp/xander_mpdf_vendor.tgz")
    print("PUT /tmp/xander_mpdf_vendor.tgz")
    sftp.close()

    cmd = r"""
set -e
mkdir -p /opt/xander-school/app/vendor /opt/xander-school/app/writable/mpdf
tar -xzf /tmp/xander_mpdf_vendor.tgz -C /opt/xander-school/app/vendor
rm -f /tmp/xander_mpdf_vendor.tgz
chmod 775 /opt/xander-school/app/writable/mpdf || true
cd /opt/xander-school/deploy
docker compose -f docker-compose.prod.yml --env-file .env.production restart app
sleep 10
docker exec xander_school_app php -r 'opcache_reset();' || true
docker exec -w /var/www/html xander_school_app composer dump-autoload --no-dev --no-interaction --no-scripts || true
docker exec xander_school_app php -l /var/www/html/app/Libraries/MpdfReport.php
docker exec xander_school_app php -l /var/www/html/app/Views/pages/reports/staff_report_clock_pdf.php
docker exec xander_school_app grep -n "MpdfReport\|staff_report_clock_pdf\|Clock in" \
  /var/www/html/app/Controllers/Home.php \
  /var/www/html/app/Libraries/MpdfReport.php \
  /var/www/html/app/Views/pages/reports/staff_report_clock_pdf.php | head -30
docker exec xander_school_app test -d /var/www/html/vendor/mpdf/mpdf
echo DONE
"""
    _, o, e = c.exec_command(cmd, timeout=300)
    sys.stdout.buffer.write(o.read())
    err = e.read().decode(errors="replace")
    if err.strip() and "Using a password" not in err:
        sys.stderr.write(err)
    code = o.channel.recv_exit_status()
    c.close()
    print("EXIT", code)
    return code


if __name__ == "__main__":
    raise SystemExit(main())
