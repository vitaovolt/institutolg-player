# institutolg-player

Biblioteca de aulas gravadas do Instituto LG (Educraft).

A coordenação envia o vídeo no painel. O aluno assiste pelo player colado na Eduq (sem login Educraft). Cópia de segurança no Google Drive do Instituto.

## Stack

- `code/backend` — Laravel 12 API (PostgreSQL, Sanctum, OpenAPI)
- `code/frontend` — SPA React + Vite + Tailwind (**não** Inertia)

## Setup local (F0)

```powershell
powershell -File ..\educraft-devkit\scripts\ensure-pgsql-db.ps1 -BackendPath "C:\Users\Admin\Documents\EDUCRAFT\institutolg-player\code\backend"
cd code\backend
php artisan migrate --force
php artisan serve
```

```powershell
cd code\frontend
npm install
npm run dev
```

- API: http://localhost:8000/api/v1/health
- SPA: http://localhost:5173

Fila local: `QUEUE_CONNECTION=database` (pack `queues`). Upload, Drive e player entram nas fases seguintes.

Processo Devkit: `../educraft-devkit/projects/institutolg-player/`

Remote: `git@github.com:vitaovolt/institutolg-player.git`

Produção (F6): ver [docs/DEPLOY.md](docs/DEPLOY.md).  
Antes de apontar DNS: [docs/ARMAZENAMENTO.md](docs/ARMAZENAMENTO.md) (play + pasta compartilhada). Self-hosted + Deploy Key; worker da fila `biblioteca`. MVP sem e-mail transacional.
