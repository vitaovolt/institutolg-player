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

            var TOKEN = @json($aula->token_publico);
            var KEY = 'ilg-player-velocidade';
            var KEY_PROGRESSO = 'ilg-player-progresso';
            var MIN_SAVE = 10;
            var END_BUFFER = 30;
            var SAVE_INTERVAL = 5000;
            var MAX_ENTRIES = 50;
            var ALLOWED = [1, 1.5, 2, 4];
            var lastSave = 0;

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

            function lerProgresso() {
                try {
                    return JSON.parse(localStorage.getItem(KEY_PROGRESSO) || '{}');
                } catch (e) {
                    return {};
                }
            }

            function gravarProgresso(data) {
                try {
                    localStorage.setItem(KEY_PROGRESSO, JSON.stringify(data));
                } catch (e) {}
            }

            function podaProgresso(data) {
                var keys = Object.keys(data);
                if (keys.length <= MAX_ENTRIES) {
                    return data;
                }
                keys.sort(function (a, b) {
                    return (data[b].at || 0) - (data[a].at || 0);
                });
                var podado = {};
                keys.slice(0, MAX_ENTRIES).forEach(function (key) {
                    podado[key] = data[key];
                });
                return podado;
            }

            function duracaoValida() {
                return isFinite(video.duration) && video.duration > MIN_SAVE + END_BUFFER;
            }

            function salvarProgresso() {
                if (!TOKEN || !duracaoValida()) {
                    return;
                }
                var tempo = video.currentTime;
                var data = lerProgresso();
                if (tempo < MIN_SAVE || tempo >= video.duration - END_BUFFER) {
                    if (data[TOKEN]) {
                        delete data[TOKEN];
                        gravarProgresso(data);
                    }
                    return;
                }
                data[TOKEN] = {
                    s: Math.round(tempo * 10) / 10,
                    at: Math.floor(Date.now() / 1000),
                };
                gravarProgresso(podaProgresso(data));
            }

            function restaurarProgresso() {
                if (!TOKEN || !duracaoValida()) {
                    return;
                }
                var entry = lerProgresso()[TOKEN];
                if (!entry || typeof entry.s !== 'number') {
                    return;
                }
                var tempo = entry.s;
                if (tempo < MIN_SAVE || tempo >= video.duration - END_BUFFER) {
                    return;
                }
                video.currentTime = Math.min(tempo, video.duration - 5);
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
            video.addEventListener('loadedmetadata', function () {
                apply(wanted());
                restaurarProgresso();
            });
            video.addEventListener('play', function () {
                apply(wanted());
                revealSpeeds();
            });
            video.addEventListener('pause', function () {
                salvarProgresso();
                revealSpeeds();
            });
            video.addEventListener('timeupdate', function () {
                var now = Date.now();
                if (now - lastSave >= SAVE_INTERVAL) {
                    lastSave = now;
                    salvarProgresso();
                }
            });
            video.addEventListener('ended', salvarProgresso);
            window.addEventListener('pagehide', salvarProgresso);
            video.addEventListener('contextmenu', function (ev) { ev.preventDefault(); });
        })();
    </script>
</body>
</html>
