# Deploy — Instituto LG Biblioteca

Padrão Educraft: [educraft-devkit/standards/DEPLOY-GITHUB.md](../../educraft-devkit/standards/DEPLOY-GITHUB.md).

**Modelo:** CI no GitHub-hosted → Deploy no **self-hosted runner** na EC2.  
**Não** abrir SSH (22) para o mundo. **Não** usar `SSH_HOST` / `SSH_USER` / `SSH_PRIVATE_KEY` no Actions.

Aluno **não** loga neste app — assiste no iframe da Eduq. Painel = coordenação.

**E-mail:** o MVP **não** envia transacional (sem convite/reset). **Não** ligar SES neste piloto.

Não citar nomes de nuvem na tela do cliente.

| Item | Valor |
|------|--------|
| Repo | `vitaovolt/institutolg-player` |
| Path | `/var/www/institutolg-player` (raiz do clone, **não** `code/backend`) |
| Stack | Laravel API (`code/backend`) + SPA (`code/frontend/dist`) no mesmo host |
| Fila | `biblioteca` · systemd `institutolg-player-queue` 24 h |
| Health | `GET /api/v1/health` → `checks.database=ok` (503 se o banco cair) |
| Domínio | https://institutolgplayer.educraft.com.br |
| EC2 | `3.95.55.127` (origem; DNS via Cloudflare, host `institutolgplayer`) |

## Fluxo

```
Push/merge em main
  → CI (ubuntu-latest: PHPUnit + npm build)
  → Deploy (self-hosted na EC2): git fetch → composer → migrate → npm build → worker → reload
```

Disparo manual: Actions → **Deploy to Production** → Run workflow.

## Secrets (repositório GitHub)

| Secret | Obrigatório? | Função |
|--------|--------------|--------|
| `DEPLOY_PATH` | sim | `/var/www/institutolg-player` |
| `REPO_DEPLOY_KEY` | não* | Fallback se faltar `~/.ssh/institutolgplayer_github` |

\*Preferido: chave **só no disco** da EC2.

**Não** criar: `SSH_HOST`, `SSH_USER`, `SSH_PRIVATE_KEY`.

## No servidor (uma vez)

### 1) Clone

```bash
sudo mkdir -p /var/www/institutolg-player
sudo chown -R ubuntu:ubuntu /var/www/institutolg-player
cd /var/www/institutolg-player
git clone git@github.com:vitaovolt/institutolg-player.git .
```

### 2) Deploy Key no disco

```bash
ssh-keygen -t ed25519 -C "institutolg-player-ec2-deploy" -f ~/.ssh/institutolgplayer_github -N ""
cat ~/.ssh/institutolgplayer_github.pub
```

GitHub → Settings → **Deploy keys** → Add (`EC2 institutolg-player`, **Allow write** desmarcado).

Teste:

```bash
GIT_SSH_COMMAND="ssh -i ~/.ssh/institutolgplayer_github -o IdentitiesOnly=yes" git fetch origin
```

### 3) Runner self-hosted

Labels `self-hosted`, `Linux`, `X64` — status **Idle**. Instalar como user `ubuntu` (nunca `sudo` no `config.sh`).

### 4) App + Security Group

1. PostgreSQL `institutolg_player` + usuário
2. `code/backend/.env` de produção (não versionar) — ver abaixo
3. `php artisan key:generate`
4. Nginx: `deploy/nginx/institutolg-player.conf` → sites-enabled + `certbot --nginx`
5. Queue: `deploy/systemd/institutolg-player-queue.service` (`enabled` + `active`)
6. Cron a cada minuto: `php artisan schedule:run` (importação da pasta compartilhada de hora em hora; o botão na Biblioteca não depende disto)
7. DNS A do domínio → IP da EC2
8. Security Group: porta **22** só no IP `/32` do admin — **nunca** `0.0.0.0/0`

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://institutolgplayer.educraft.com.br
FRONTEND_URL=https://institutolgplayer.educraft.com.br
DB_CONNECTION=pgsql
DB_DATABASE=institutolg_player
QUEUE_CONNECTION=database
BIBLIOTECA_AULAS_DRIVER=s3
BIBLIOTECA_DRIVE_FAKE=false
BIBLIOTECA_DRIVE_SERVICE_ACCOUNT_PATH=/var/www/institutolg-player/secrets/drive-sa.json
BIBLIOTECA_DRIVE_FOLDER_ID=
BIBLIOTECA_DRIVE_TIMEOUT=600
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=auto
AWS_BUCKET=institutolg-player-aulas
AWS_ENDPOINT=https://ACCOUNT_ID.r2.cloudflarestorage.com
AWS_USE_PATH_STYLE_ENDPOINT=true
MAIL_MAILER=log
```

`FRONTEND_URL` **obrigatório** em production (CORS; a API recusa boot se faltar).  
SPA build: `VITE_API_URL=/api/v1` (mesmo domínio via Nginx).  
`APP_URL` certo: senão a mídia assinada do player quebra.

**Play + cópia Drive:** não deixe `BIBLIOTECA_AULAS_DRIVER=local` nem `BIBLIOTECA_DRIVE_FAKE=true` em produção. Siga [ARMAZENAMENTO.md](ARMAZENAMENTO.md) **antes** de apontar o DNS.

PHP-FPM **não** recebe o MP4 de 35 GB (a EC2 tem ~2 GB de RAM). O painel sobe o arquivo **por partes direto no objeto**. Memória do PHP neste host:

```
memory_limit = 512M
max_execution_time = 3600
```

Nginx `client_max_body_size 35g` cobre PUT único **só em local/dev**. Em produção com proxy, o binário grande não passa pelo origin.

Worker da cópia (arquivo grande): `--timeout=43200 --max-time=43200`.

## Artefatos no repo

- `.github/workflows/ci.yml` · `deploy.yml`
- `deploy/nginx/institutolg-player.conf` — **`/api` + `/assistir` + `/eduq` + `/capa`** no PHP-FPM
- `deploy/systemd/institutolg-player-queue.service` — `queue:work --queue=biblioteca`

## Smoke pós-deploy

```bash
curl -sS https://institutolgplayer.educraft.com.br/api/v1/health
# esperado: "status":"ok", "checks":{"database":"ok"}
# se o banco cair: HTTP 503 e checks.database=fail
```

```bash
sudo systemctl is-active institutolg-player-queue
# esperado: active
```

Checklist: login SPA (`carolina@institutolg.local` / `password` no seed) · health 200 · `/assistir/{token}` **sem** `X-Frame-Options: DENY` · iframe da Eduq toca o vídeo.

### Security Group

Porta **22** só no IP `/32` do admin. 80/443 abertos.
