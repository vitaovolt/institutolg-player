<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $aula->titulo }} · Instituto LG</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand-primary: #10056B;
            --brand-accent: #11D7E1;
            --brand-ink: #120F24;
            --brand-muted: #5C5870;
            --brand-bg: #120F24;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; background: var(--brand-bg); color: #fff; font-family: "Plus Jakarta Sans", system-ui, sans-serif; }
        .shell { min-height: 100vh; display: flex; flex-direction: column; padding: 12px; }
        .kicker { margin: 0 0 8px; font-size: 0.72rem; letter-spacing: .12em; text-transform: uppercase; color: var(--brand-accent); font-weight: 800; }
        h1 { margin: 0 0 12px; font-size: 1.05rem; font-weight: 800; }
        video { width: 100%; max-height: calc(100vh - 88px); background: #000; border-radius: 10px; }
        .hint { margin: 10px 0 0; font-size: .75rem; color: #c8c4d6; }
        @media (max-width: 480px) {
            .shell { padding: 8px; }
            h1 { font-size: .95rem; }
            video { max-height: calc(100svh - 72px); }
        }
    </style>
</head>
<body>
    <main class="shell">
        <p class="kicker">Aula gravada</p>
        <h1>{{ $aula->titulo }}</h1>
        <video
            data-testid="player-video"
            controls
            playsinline
            controlslist="nodownload noplaybackrate"
            disablepictureinpicture
            oncontextmenu="return false"
            src="{{ $urlMidia }}"
            @if ($urlCapa) poster="{{ $urlCapa }}" @endif
        >
            Seu navegador não reproduz este vídeo.
        </video>
        <p class="hint">Assistir nesta tela. Sem arquivo para baixar.</p>
    </main>
</body>
</html>
