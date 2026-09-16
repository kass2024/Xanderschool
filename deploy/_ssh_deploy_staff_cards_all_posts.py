#!/usr/bin/env python3
"""Deploy All-posts option on staff card generation."""
from __future__ import annotations

import os
import time
from pathlib import Path

import paramiko

ROOT = Path(r"C:\xampp7\htdocs\Xander-school")
HOST = "66.29.135.120"
USER = "root"
PASSWORD = os.environ.get("VPS_PASSWORD", "6W7sa2g4dMEwcN80ZU")

FILES = [
    "app/Views/pages/staff_cards.php",
    "app/Language/en/app.php",
    "app/Language/fr/app.php",
    "app/Models/StaffModel.php",
]

OLD = (
    '$key = $isPost == 0 ? "staffs.id" : "p.id";\n'
    "\t\t$staffs = $StaffModel->get_staff($key . '=' . $id);"
)
NEW = "$staffs = $StaffModel->get_staff_for_card_list($id, $isPost);"


def patch_home(text: str) -> str:
    if "get_staff_for_card_list" in text:
        return text
    if OLD not in text:
        # CRLF remote copy
        old_crlf = OLD.replace("\n", "\r\n")
        if old_crlf in text:
            return text.replace(old_crlf, NEW, 1)
        raise SystemExit("get_staffs lookup block not found on remote Home.php")
    return text.replace(OLD, NEW, 1)


def main() -> int:
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(HOST, username=USER, password=PASSWORD, timeout=180, banner_timeout=180, auth_timeout=180)
    sftp = client.open_sftp()
    for rel in FILES:
        local = ROOT / rel
        remote = f"/opt/xander-school/app/{rel}".replace("\\", "/")
        parent = remote.rsplit("/", 1)[0]
        client.exec_command(f"mkdir -p {parent}")
        time.sleep(0.05)
        sftp.put(str(local), remote)
        print("PUT", rel)

    remote_home = "/opt/xander-school/app/app/Controllers/Home.php"
    with sftp.open(remote_home, "r") as fh:
        home = fh.read().decode("utf-8")
    patched = patch_home(home)
    if patched != home:
        tmp_local = ROOT / "deploy" / "_Home.php.staff_all_posts.tmp"
        tmp_local.write_text(patched, encoding="utf-8", newline="\n")
        sftp.put(str(tmp_local), remote_home)
        tmp_local.unlink(missing_ok=True)
        print("PATCH Home.php get_staff_for_card_list")
    else:
        print("Home.php already uses get_staff_for_card_list")
    sftp.close()

    cmd = r"""
set -e
docker exec xander_school_app grep -n "allPosts\|get_staff_for_card_list" \
  /var/www/html/app/Views/pages/staff_cards.php \
  /var/www/html/app/Language/en/app.php \
  /var/www/html/app/Models/StaffModel.php \
  /var/www/html/app/Controllers/Home.php | head -n 40
cd /opt/xander-school/deploy && docker compose -f docker-compose.prod.yml --env-file .env.production restart app
sleep 4
docker exec xander_school_app php -r "opcache_reset(); echo 'OPCACHE_RESET_OK\n';"
"""
    _, stdout, stderr = client.exec_command(cmd, timeout=180)
    print(stdout.read().decode())
    err = stderr.read().decode()
    if err.strip():
        print(err)
    client.close()
    print("Deploy complete.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
