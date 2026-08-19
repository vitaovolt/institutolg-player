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
        .player-wrap { position: relative; }
        video { width: 100%; max-height: calc(100vh - 88px); background: #000; border-radius: 10px; display: block; }
        .speeds {
            position: absolute;
            top: 8px;
            right: 8px;
            display: flex;
            gap: 4px;
            z-index: 2;
        }
        .speeds button {
            font-family: inherit;
            font-size: .7rem;
            font-weight: 800;
            letter-spacing: .02em;
            padding: 5px 8px;
            border-radius: 6px;
            border: 1px solid rgba(17, 215, 225, .45);
            background: rgba(18, 15, 36, .78);
            color: #fff;
            cursor: pointer;
        }
        .speeds button[aria-pressed="true"] {
            background: var(--brand-accent);
            color: var(--brand-ink);
            border-color: var(--brand-accent);
        }
        .speeds button:focus-visible {
            outline: 2px solid var(--brand-accent);
            outline-offset: 2px;
        }
        .hint { margin: 10px 0 0; font-size: .75rem; color: #c8c4d6; }
        @media (max-width: 480px) {
            .shell { padding: 8px; }
            h1 { font-size: .95rem; }
            video { max-height: calc(100svh - 72px); }
            .speeds button { font-size: .65rem; padding: 4px 6px; }
        }
    </style>
</head>
<body>
    <main class="shell">
        <p class="kicker">Aula gravada</p>
        <h1>{{ $aula->titulo }}</h1>
        <div class="player-wrap">
            <video
                data-testid="player-video"
                controls
                playsinline
                controlslist="nodownload noplaybackrate"
                disablepictureinpicture
                src="{{ $urlMidia }}"
                @if ($urlCapa) poster="{{ $urlCapa }}" @endif
            >
                Seu navegador não reproduz este vídeo.
            </video>
            <div class="speeds" data-testid="player-speeds" role="group" aria-label="Velocidade do vídeo">
                <button type="button" data-testid="player-speed-1" data-rate="1" aria-pressed="true">1x</button>
                <button type="button" data-testid="player-speed-1-5" data-rate="1.5" aria-pressed="false">1,5x</button>
                <button type="button" data-testid="player-speed-2" data-rate="2" aria-pressed="false">2x</button>
                <button type="button" data-testid="player-speed-4" data-rate="4" aria-pressed="false">4x</button>
            </div>
        </div>
        <p class="hint">Assistir nesta tela. Sem arquivo para baixar. Velocidade: 1x, 1,5x, 2x ou 4x.</p>
    </main>
    <script nonce="{{ $cspNonce ?? '' }}">
        (function () {
            var video = document.querySelector('[data-testid="player-video"]');
            var group = document.querySelector('[data-testid="player-speeds"]');
            if (!video || !group) {
                return;
            }

            var KEY = 'ilg-player-velocidade';
            var ALLOWED = [1, 1.5, 2, 4];

            function parseRate(value) {
                var rate = parseFloat(value);
                return ALLOWED.indexOf(rate) >= 0 ? rate : 1;
            }

            function wanted() {
                try {
                    return parseRate(localStorage.getItem(KEY));
                } catch (e) {
                    return 1;
                }
            }

            function mark(rate) {
                group.querySelectorAll('button[data-rate]').forEach(function (btn) {
                    btn.setAttribute('aria-pressed', parseRate(btn.getAttribute('data-rate')) === rate ? 'true' : 'false');
                });
            }

            function apply(rate) {
                rate = parseRate(rate);
                video.playbackRate = rate;
                mark(rate);
                try {
                    localStorage.setItem(KEY, String(rate));
                } catch (e) {}
            }

            apply(wanted());
            group.addEventListener('click', function (ev) {
                var btn = ev.target.closest('button[data-rate]');
                if (!btn) {
                    return;
                }
                apply(btn.getAttribute('data-rate'));
            });
            video.addEventListener('loadedmetadata', function () { apply(wanted()); });
            video.addEventListener('play', function () { apply(wanted()); });
            video.addEventListener('contextmenu', function (ev) { ev.preventDefault(); });
        })();
    </script>
</body>
</html>
