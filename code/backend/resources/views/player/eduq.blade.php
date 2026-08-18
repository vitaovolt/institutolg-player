<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Eduq · {{ $aula->titulo }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --eduq: #2bb0c7; --ink: #120F24; --muted: #5C5870; --line: #E2DFEA; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: "Plus Jakarta Sans", system-ui, sans-serif; color: var(--ink); background: #eef7f9; }
        .bar { display: flex; justify-content: space-between; gap: 12px; background: var(--eduq); color: #fff; padding: 12px 16px; font-size: .85rem; font-weight: 700; }
        .body { max-width: 860px; margin: 0 auto; padding: 20px 16px 40px; }
        .kicker { font-size: .75rem; letter-spacing: .08em; text-transform: uppercase; color: var(--eduq); font-weight: 800; }
        h1 { margin: 6px 0 10px; font-size: 1.45rem; }
        .text { color: var(--muted); margin: 0 0 16px; }
        .frame-wrap { background: #fff; border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
        iframe { display: block; width: 100%; height: 480px; border: 0; background: #120F24; }
        .hint { margin: 12px 0 0; font-size: .8rem; color: var(--muted); }
        .phone { max-width: 360px; margin: 22px auto 0; border: 10px solid #1b1730; border-radius: 28px; background: #fff; }
        .phone iframe { height: 220px; }
        @media (max-width: 480px) {
            .phone { display: none; }
            iframe { height: 56vw; min-height: 220px; }
        }
    </style>
</head>
<body>
    <div class="bar">
        <span>Eduq · {{ $aula->disciplina?->turma?->curso?->nome ?? 'Curso' }}</span>
        <span>Aluno · {{ $aula->disciplina?->turma?->nome ?? 'Turma' }}</span>
    </div>
    <main class="body" data-testid="mock-eduq">
        <p class="kicker">{{ $aula->disciplina?->nome ?? 'Disciplina' }} · Aula gravada</p>
        <h1>{{ $aula->titulo }}</h1>
        <p class="text">Texto e materiais da aula continuam na Eduq. Abaixo entra o vídeo da biblioteca (bloco Vídeo → Iframe).</p>
        <div class="frame-wrap">
            <iframe data-testid="iframe-player" src="{{ $srcPlayer }}" allowfullscreen title="Player da aula"></iframe>
        </div>
        <p class="hint">O aluno não entra no painel Instituto LG. Sem segundo login. Sem botão de download.</p>
        <div class="phone" aria-hidden="true">
            <iframe src="{{ $srcPlayer }}" title="Player no celular"></iframe>
        </div>
    </main>
</body>
</html>
