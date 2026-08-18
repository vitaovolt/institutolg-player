#!/bin/bash
set -euo pipefail

DOMAIN=institutolgplayer.educraft.com.br
ROOT=/var/www/institutolg-player
BE="$ROOT/code/backend"
FE="$ROOT/code/frontend"

echo "=== Node 20 ==="
if ! node -v | grep -qE '^v20\.|^v2[1-9]\.|^v[3-9]'; then
  curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
  sudo apt-get install -y nodejs
fi
node -v
npm -v

echo "=== database ==="
DB_PASS=$(openssl rand -base64 24 | tr -d '/+=' | head -c 24)
sudo -u postgres psql -v ON_ERROR_STOP=1 <<SQL
DO \$\$
BEGIN
  IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'institutolg_player') THEN
    CREATE ROLE institutolg_player LOGIN PASSWORD '${DB_PASS}';
  ELSE
    ALTER ROLE institutolg_player WITH LOGIN PASSWORD '${DB_PASS}';
  END IF;
END
\$\$;
SQL
sudo -u postgres psql -tc "SELECT 1 FROM pg_database WHERE datname='institutolg_player'" | grep -q 1 \
  || sudo -u postgres psql -c "CREATE DATABASE institutolg_player OWNER institutolg_player;"
sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE institutolg_player TO institutolg_player;"
sudo -u postgres psql -d institutolg_player -c "GRANT ALL ON SCHEMA public TO institutolg_player;" || true
umask 077
printf '%s' "$DB_PASS" > /tmp/ilg_db_pass.txt

echo "=== .env ==="
cd "$BE"
if [ ! -f .env ]; then
  cp .env.example .env
fi
python3 - <<'PY'
from pathlib import Path
p = Path('.env')
text = p.read_text()
lines = text.splitlines()
kv = {}
order = []
for line in lines:
    if not line.strip() or line.strip().startswith('#') or '=' not in line:
        order.append(('raw', line))
        continue
    k, _, v = line.partition('=')
    kv[k] = v
    order.append(('kv', k))

updates = {
  'APP_NAME': '"Instituto LG Biblioteca"',
  'APP_ENV': 'production',
  'APP_DEBUG': 'false',
  'APP_URL': 'https://institutolgplayer.educraft.com.br',
  'FRONTEND_URL': 'https://institutolgplayer.educraft.com.br',
  'LOG_LEVEL': 'error',
  'DB_CONNECTION': 'pgsql',
  'DB_HOST': '127.0.0.1',
  'DB_PORT': '5432',
  'DB_DATABASE': 'institutolg_player',
  'DB_USERNAME': 'institutolg_player',
  'DB_PASSWORD': Path('/tmp/ilg_db_pass.txt').read_text().strip(),
  'QUEUE_CONNECTION': 'database',
  'MAIL_MAILER': 'log',
  'BIBLIOTECA_AULAS_DRIVER': 'local',
  'BIBLIOTECA_DRIVE_FAKE': 'true',
  'AWS_USE_PATH_STYLE_ENDPOINT': 'true',
  'AWS_DEFAULT_REGION': 'auto',
}
for k, v in updates.items():
    kv[k] = v

out = []
seen = set()
for kind, val in order:
    if kind == 'raw':
        out.append(val)
    else:
        out.append(f'{val}={kv[val]}')
        seen.add(val)
for k, v in updates.items():
    if k not in seen:
        out.append(f'{k}={v}')
p.write_text('\n'.join(out) + '\n')
print('env_written')
PY
rm -f /tmp/ilg_db_pass.txt
chmod 600 .env

echo "=== composer + migrate ==="
composer install --no-dev --optimize-autoloader --no-interaction
if ! grep -q '^APP_KEY=base64:' .env; then
  php artisan key:generate --force
fi
php artisan migrate --force
php artisan db:seed --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo systemctl enable --now postgresql@15-main 2>/dev/null || sudo systemctl enable --now postgresql
sudo chown -R ubuntu:www-data "$ROOT"
sudo find "$ROOT" -type d -exec chmod 775 {} \;
sudo find "$ROOT" -type f -exec chmod 664 {} \;
sudo chmod -R 775 "$BE/storage" "$BE/bootstrap/cache"
sudo chmod 640 "$BE/.env"
sudo chmod +x "$BE/artisan"

echo "=== frontend ==="
cd "$FE"
npm ci
VITE_API_URL=/api/v1 npm run build

echo "=== php.ini (2 GB RAM: teto 512M neste host) ==="
sudo sed -i 's/^memory_limit = .*/memory_limit = 512M/' /etc/php/8.2/fpm/php.ini
sudo sed -i 's/^max_execution_time = .*/max_execution_time = 3600/' /etc/php/8.2/fpm/php.ini
sudo sed -i 's/^post_max_size = .*/post_max_size = 512M/' /etc/php/8.2/fpm/php.ini
sudo sed -i 's/^upload_max_filesize = .*/upload_max_filesize = 512M/' /etc/php/8.2/fpm/php.ini
sudo sed -i 's/^memory_limit = .*/memory_limit = 512M/' /etc/php/8.2/cli/php.ini
sudo systemctl reload php8.2-fpm

echo "=== nginx + queue ==="
sudo cp "$ROOT/deploy/nginx/institutolg-player.conf" /etc/nginx/sites-available/institutolg-player.conf
sudo ln -sf /etc/nginx/sites-available/institutolg-player.conf /etc/nginx/sites-enabled/institutolg-player.conf
sudo rm -f /etc/nginx/sites-enabled/default-closed
sudo nginx -t
sudo systemctl reload nginx
sudo cp "$ROOT/deploy/systemd/institutolg-player-queue.service" /etc/systemd/system/institutolg-player-queue.service
sudo systemctl daemon-reload
sudo systemctl enable --now institutolg-player-queue

echo "=== git remote SSH (deploy key) ==="
cd "$ROOT"
git remote set-url origin git@github.com:vitaovolt/institutolg-player.git

echo "=== local smoke ==="
curl -sS -H "Host: $DOMAIN" http://127.0.0.1/api/v1/health || true
echo
systemctl is-active institutolg-player-queue
echo DONE
