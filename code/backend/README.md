# Backend — Instituto LG Biblioteca

Laravel 12 API-only (DNA Educraft desacoplado — **sem** Inertia).

## Stack

- PHP 8.2+ · Laravel 12 · Sanctum · PostgreSQL
- Rotas: `/api/v1/*`
- OpenAPI: `docs/openapi.yaml`

## Setup local

```powershell
copy .env.example .env
php artisan key:generate
powershell -File ..\..\..\educraft-devkit\scripts\ensure-pgsql-db.ps1 -BackendPath .
php artisan migrate --force
php artisan serve
```

Health: `GET http://localhost:8000/api/v1/health` — inclui `checks.database` (503 se o banco falhar).

## Estrutura

- Controllers: `app/Http/Controllers/Api`
- Auth Sanctum (`HasApiTokens`) — login na F2
- Fila: `QUEUE_CONNECTION=database` (tabelas `jobs` / `failed_jobs`). Worker entra quando houver jobs de preparar versão e Drive
- Domínio: Curso → Turma → Disciplina → Aula; resumo do mês em `GET /api/v1/resumo-mes`
- CORS: só `FRONTEND_URL` (+ localhost em `local`); Bearer sem `statefulApi`
