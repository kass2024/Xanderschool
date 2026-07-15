# Deploy Xander-school (CodeIgniter 4 / PHP 7.4) on VPS — Docker

Same safety model as E-Learning Xander:

| Rule | Detail |
|------|--------|
| Install path | Only `/opt/xander-school` — **never** `/var/www/...` |
| Ports | Docker publishes **`8091` only** — never public `:80` / `:443` |
| Apache | Existing sites in `/var/www` untouched; optional new proxy vhost later |
| Docker | Separate compose project `xander-school` — does not share network/volumes with `parrot_*` |

## Layout

```text
/opt/xander-school/
  app/                 # CodeIgniter application
  deploy/
    docker-compose.prod.yml
    Dockerfile
    .env.production
    db/iotxa_db.sql
```

## Access

- Immediate: `http://VPS_IP:8091/`
- Optional domain later: `scripts/setup-apache-proxy.sh`

## Update

```bash
cd /opt/xander-school/deploy
docker compose -f docker-compose.prod.yml --env-file .env.production up -d --build
```
