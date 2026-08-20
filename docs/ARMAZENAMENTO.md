# Armazenamento — o que contratar e como ligar (antes da produção)

Documento **interno** (operador Educraft). Na tela do Instituto: **não** citar nomes de nuvem.

São **dois caminhos**. Não é “um serviço só de sync”.

| Caminho | Para que serve | Quem contrata / já tem | O aluno usa? |
|---------|----------------|------------------------|--------------|
| **Play** (objeto da Educraft) | Arquivo que o player toca (URL temporária) | **Educraft** contrata o armazenamento de objetos | Sim |
| **Cópia** (pasta do Instituto) | Backup nos 24 TB deles | Instituto **já tem** o Drive. Carolina **compartilha** uma pasta | Não |

Se a cópia falhar, a aula **pode continuar assistível**. Os dois status no painel são independentes.

Há dois botões (sentidos opostos):

| Botão | Sentido |
|-------|---------|
| **Sincronizar com Google Drive** (no detalhe da aula) | play → pasta |
| **Importar da pasta compartilhada** (na Biblioteca) | pasta → cadastro + play |

A importação lê **3 níveis** a partir da pasta raiz: curso → turma → disciplina → MP4. Não publica sozinha. R$ 3,80 vale no `enviado_em`. Recorrente: o mesmo job na fila `biblioteca` + `schedule:run` a cada hora (além do botão).

## O que **não** contratar

| Evitar | Por quê |
|--------|---------|
| Player que cobra **por minuto/visualização** | O cliente recusou fatura que sobe com play |
| Armazenamento que cobra **banda na saída** como origem do play | A mensalidade não inclui GB assistido |
| Mais espaço no Drive do Instituto | Eles já têm 24 TB; Drive **não** é o player |
| Subir o arquivo de **edição** (~45 GB) | Painel aceita export MP4 de **até 35 GB**. O projeto do editor continua grande demais |

Local hoje: disco do Laravel + cópia “fake” (arquivo numa pasta local). Produção **não** pode ir ao ar assim: o MP4 ficaria no disco da EC2.

## Ordem (não pule)

1. Conta da **Educraft** no painel do armazenamento de objetos (não a conta do Instituto).
2. Bucket **privado** + token de API (Access Key + Secret).
3. CORS no bucket (o `<video>` pede o arquivo noutra origem).
4. Colar as chaves no `.env` da API (nunca no Git).
5. Pasta no Drive do Instituto + conta de serviço Google da Educraft.
6. Um MP4 **pequeno** de teste (status Pronta + cópia Ok).
7. Só então o go-live da EC2 (`docs/DEPLOY.md`).

Papel do agente: este roteiro + `.env`. **Você** cria bucket/token/pasta no **navegador**. Não use CLI de nuvem do PC da Educraft sem conferir a conta (já houve incidente com conta errada).

---

## A) Play — armazenamento de objetos (Educraft)

Compatível com API estilo S3. Região do endpoint: a que o painel mostrar (em geral `auto`).

### A1. Criar o bucket

1. Entre no painel da **conta Educraft** (Account ID no canto — anote).
2. **R2** (ou Storage) → **Create bucket**.
3. Nome sugerido: `institutolg-player-aulas`.
4. Localização: a default do painel.
5. **Public access:** desligado. Ninguém lista o bucket. O aluno só entra com URL **assinada e temporária**.

### A2. Token de API (Access Key)

1. **Manage R2 API Tokens** → **Create API token**.
2. Permissão: **Object Read & Write** só neste bucket (não “Admin” da conta).
3. Copie **uma vez**: Access Key ID + Secret Access Key.
4. Endpoint S3 (troque o Account ID):

```
https://<ACCOUNT_ID>.r2.cloudflarestorage.com
```

### A3. CORS no bucket

O player Educraft está no seu domínio; o arquivo está no bucket. Sem CORS o vídeo não toca no iframe.

No bucket → **Settings** → **CORS policy** (JSON). Troque o domínio:

```json
[
  {
    "AllowedOrigins": ["https://institutolgplayer.educraft.com.br"],
    "AllowedMethods": ["GET", "HEAD", "PUT", "POST"],
    "AllowedHeaders": ["*"],
    "ExposeHeaders": ["Content-Length", "Content-Type", "ETag"],
    "MaxAgeSeconds": 3600
  }
]
```

Em local (só se for testar objeto de verdade no PC): inclua `http://127.0.0.1:8000` e `http://localhost:5173`.

MP4 grande (até 35 GB): o navegador faz PUT **direto no bucket** (partes). Sem `PUT`/`POST` no CORS o envio falha. `ExposeHeaders` precisa de `ETag`.

### A4. Colar no `.env` da API (`code/backend/.env` — produção)

```env
BIBLIOTECA_AULAS_DRIVER=s3
BIBLIOTECA_DISK_AULAS=aulas

AWS_ACCESS_KEY_ID=cole_aqui
AWS_SECRET_ACCESS_KEY=cole_aqui
AWS_DEFAULT_REGION=auto
AWS_BUCKET=institutolg-player-aulas
AWS_ENDPOINT=https://ACCOUNT_ID.r2.cloudflarestorage.com
AWS_USE_PATH_STYLE_ENDPOINT=true
```

Depois: `php artisan config:cache` e **reiniciar** o worker `institutolg-player-queue`.

O pacote `league/flysystem-aws-s3-v3` já entra no `composer.json` deste repo.

### A5. Conferir o play

```powershell
cd C:\Users\Admin\Documents\EDUCRAFT\institutolg-player\code\backend
php artisan tinker
```

```php
Storage::disk('aulas')->put('ping.txt', 'ok');
Storage::disk('aulas')->get('ping.txt'); // "ok"
Storage::disk('aulas')->temporaryUrl('ping.txt', now()->addMinutes(5));
```

A URL temporária deve abrir o `ok` no navegador. Apague o ping: `Storage::disk('aulas')->delete('ping.txt');`

No player: o `<video src>` aponta para essa URL assinada (não para o disco da EC2).

### A6. Árvore de pastas (vídeo e capa)

Ao enviar, o sistema grava **curso → turma → disciplina → arquivo**. A capa fica na **mesma pasta** da disciplina, ao lado do vídeo.

No objeto do play isso é o próprio caminho da chave (slug sem acento). O PUT já “cria” o prefixo — não há pasta vazia para criar antes.

Exemplo:

```
pos-graduacao-em-saude/turma-2026-a/cardiologia/aula-04-novo-tema.mp4
pos-graduacao-em-saude/turma-2026-a/cardiologia/aula-04-novo-tema_capa.png
```

No Drive (cópia) as pastas usam o **nome cadastrado**. Se a pasta já existe, reusa; se não, cria. Vídeo e capa entram na pasta da disciplina.

---

## B) Cópia — pasta compartilhada do Instituto (já existe)

O Instituto **não** precisa contratar Drive. Os 24 TB já estão lá.

O que falta: uma **pasta** só desta biblioteca + uma **conta de serviço** da Educraft com permissão de gravar nela.

### B1. Google Cloud (Educraft, no navegador)

1. [Google Cloud Console](https://console.cloud.google.com/) na conta Educraft.
2. Crie um projeto (ex.: `educraft-institutolg-player`).
3. **APIs & Services** → **Enable APIs** → **Google Drive API**.
4. **IAM & Admin** → **Service accounts** → **Create**.
   - Nome: `institutolg-player-drive`
   - Sem papel de projeto (não precisa ser Owner).
5. Na conta criada → **Keys** → **Add key** → JSON. Baixe o arquivo.
6. Copie o e-mail da conta de serviço (`...@....iam.gserviceaccount.com`).

Guarde o JSON **só no servidor** (não no Git):

```bash
sudo mkdir -p /var/www/institutolg-player/secrets
sudo mv /caminho/do/json /var/www/institutolg-player/secrets/drive-sa.json
sudo chown www-data:www-data /var/www/institutolg-player/secrets/drive-sa.json
sudo chmod 640 /var/www/institutolg-player/secrets/drive-sa.json
```

### B2. Pasta no Drive do Instituto (Carolina)

A conta de serviço **não tem cota** no “Meu Drive”. A pasta da biblioteca precisa ser um **Drive compartilhado** (Shared drive), não uma pasta comum compartilhada por link.

1. No Drive dos 24 TB, criar um **Drive compartilhado** (ou uma pasta dentro dele), ex.: `Biblioteca aulas — Educraft`.
2. **Gerenciar membros** → adicionar o e-mail da conta de serviço → permissão **Administrador de conteúdo** (ou Editor).
3. Abrir a pasta no navegador; o ID está na URL:

```
https://drive.google.com/drive/folders/ESTE_ID_AQUI
```

Mande esse ID para colar no `.env` (`BIBLIOTECA_DRIVE_FOLDER_ID`).

### B3. `.env` da cópia

```env
BIBLIOTECA_DRIVE_FAKE=false
BIBLIOTECA_DRIVE_SERVICE_ACCOUNT_PATH=/var/www/institutolg-player/secrets/drive-sa.json
BIBLIOTECA_DRIVE_FOLDER_ID=ESTE_ID_AQUI
BIBLIOTECA_DRIVE_TIMEOUT=600
```

Deixe `BIBLIOTECA_DRIVE_UPLOAD_URL` **vazio** (isso é só o modo de teste HTTP).

Reinicie o worker da fila.

### B4. Conferir a cópia

1. No painel, envie um MP4 **pequeno** e espere **Pronta**.
2. No detalhe da aula, clique em **Sincronizar com Google Drive** (a cópia **não** sobe sozinha após o envio).
3. Status da cópia deve ir para **Ok**.
4. Abra a pasta no Drive: deve existir **Curso → Turma → Disciplina**; o MP4 e a capa (se houver) ficam dentro da disciplina, com o título da aula.

Se a cópia der erro e a aula estiver Pronta: o play está ok; use o mesmo botão de novo. Causas típicas: pasta não compartilhada com o e-mail da conta de serviço, Drive API desligada, JSON no path errado. A exclusão no painel **não** apaga a pasta compartilhada.

---

## C) Local (PC) — não precisa contratar nada

```env
BIBLIOTECA_AULAS_DRIVER=local
BIBLIOTECA_DRIVE_FAKE=true
```

Cópia “Ok” no local = arquivo em `storage/app/private/drive/`, **não** no Drive real.

---

## D) Checklist antes de apontar DNS de produção

- [ ] Bucket privado + token só deste bucket
- [ ] CORS com o domínio do player
- [ ] `tinker` `temporaryUrl` abre o ping
- [ ] Pasta Drive compartilhada com o e-mail da conta de serviço
- [ ] JSON da conta de serviço no servidor, fora do Git
- [ ] Worker `institutolg-player-queue` **active**
- [ ] 1 aula de teste: Pronta + cópia Ok + iframe toca
- [ ] Nenhuma tela do painel cita nomes de nuvem

Quando isto estiver verde: voltar ao `docs/DEPLOY.md` (EC2, Nginx, secret `DEPLOY_PATH`).
