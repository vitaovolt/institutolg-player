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
            right: 8px;
            bottom: 96px;
            display: flex;
            gap: 2px;
            z-index: 2;
            opacity: 0;
            pointer-events: none;
            transition: opacity .18s ease;
        }
        .player-wrap:hover .speeds,
        .player-wrap:focus-within .speeds,
        .player-wrap.is-controls .speeds {
            opacity: 1;
            pointer-events: auto;
        }
        .speeds button {
            font-family: inherit;
            font-size: .62rem;
            font-weight: 700;
            letter-spacing: .01em;
            padding: 3px 7px;
            border-radius: 4px;
            border: 0;
            background: transparent;
            color: rgba(255, 255, 255, .72);
            cursor: pointer;
            text-shadow: 0 1px 2px rgba(0, 0, 0, .7);
        }
        .speeds button[aria-pressed="true"] {
            color: #fff;
            box-shadow: inset 0 -2px 0 var(--brand-accent);
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
            .speeds { bottom: 88px; right: 6px; }
            .speeds button { font-size: .6rem; padding: 3px 6px; }
        }
    </style>
</head>
<body>
    <main class="shell">
        <p class="kicker">Aula gravada</p>
        <h1>{{ $aula->titulo }}</h1>
        <div class="player-wrap" data-testid="player-wrap">
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
        <p class="hint">Assistir nesta tela. Sem arquivo para baixar.</p>
    </main>
    <script nonce="{{ $cspNonce ?? '' }}">
        (function () {
            var video = document.querySelector('[data-testid="player-video"]');
            var group = document.querySelector('[data-testid="player-speeds"]');
            var wrap = document.querySelector('[data-testid="player-wrap"]');
            if (!video || !group || !wrap) {
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

            var hideTimer = null;

            function revealSpeeds() {
                wrap.classList.add('is-controls');
                clearTimeout(hideTimer);
                if (!video.paused) {
                    hideTimer = setTimeout(function () {
                        wrap.classList.remove('is-controls');
                    }, 2200);
                }
            }

            apply(wanted());
            group.addEventListener('click', function (ev) {
                var btn = ev.target.closest('button[data-rate]');
                if (!btn) {
                    return;
                }
                apply(btn.getAttribute('data-rate'));
                revealSpeeds();
            });
            wrap.addEventListener('mousemove', revealSpeeds);
            wrap.addEventListener('pointerdown', revealSpeeds);
            wrap.addEventListener('mouseleave', function () {
                clearTimeout(hideTimer);
                wrap.classList.remove('is-controls');
            });
            video.addEventListener('loadedmetadata', function () { apply(wanted()); });
            video.addEventListener('play', function () {
                apply(wanted());
                revealSpeeds();
            });
            video.addEventListener('pause', revealSpeeds);
            video.addEventListener('contextmenu', function (ev) { ev.preventDefault(); });
        })();
    </script>
</body>
</html>
