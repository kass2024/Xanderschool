#!/usr/bin/env python3
import paramiko
from pathlib import Path

ROOT = Path(r"C:\xampp7\htdocs\Xander-school")
FILES = [
    "app/Models/StudentVisitorModel.php",
    "app/Controllers/Api.php",
]

c = paramiko.SSHClient()
c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
c.connect("66.29.135.120", username="root", password="6W7sa2g4dMEwcN80ZU", timeout=90)
sftp = c.open_sftp()
for rel in FILES:
    sftp.put(str(ROOT / rel), f"/opt/xander-school/app/{rel}")
    print("PUT", rel)
sftp.close()
cmd = r"""
docker exec xander_school_app php -l /var/www/html/app/Models/StudentVisitorModel.php
docker exec xander_school_app php -l /var/www/html/app/Controllers/Api.php
cd /opt/xander-school/deploy
docker compose -f docker-compose.prod.yml --env-file .env.production restart app
sleep 3
docker exec xander_school_app php -r 'opcache_reset();' || true
grep -n "Targeted sibling expansion\|LOWER(TRIM(father))" /opt/xander-school/app/app/Controllers/Api.php /opt/xander-school/app/app/Models/StudentVisitorModel.php | head -20
echo DONE
"""
_, o, e = c.exec_command(cmd, timeout=180)
print(o.read().decode())
print(e.read().decode())
print("EXIT", o.channel.recv_exit_status())
c.close()
