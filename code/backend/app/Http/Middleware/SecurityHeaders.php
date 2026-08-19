<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if ($this->ehPlayerPublico($request)) {
            // Aluno assiste num iframe da Eduq (outra origem). DENY/same-site quebra o embed.
            $response->headers->remove('X-Frame-Options');
            $response->headers->set('Cross-Origin-Resource-Policy', 'cross-origin');
            $objetos = $this->origensDoObjetoDePlay();
            $midia = implode(' ', array_merge(["'self'"], $objetos));
            $imagens = implode(' ', array_merge(["'self'", 'data:'], $objetos));
            $response->headers->set(
                'Content-Security-Policy',
                "default-src 'self'; media-src {$midia}; img-src {$imagens}; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; frame-src 'self'; frame-ancestors *; base-uri 'self'"
            );
        } else {
            $response->headers->set('X-Frame-Options', 'DENY');
            $response->headers->set('Cross-Origin-Resource-Policy', 'same-site');

            if ($request->is('api/*')) {
                $response->headers->set(
                    'Content-Security-Policy',
                    "default-src 'none'; frame-ancestors 'none'; base-uri 'none'"
                );
            }
        }

        if (config('app.env') === 'production' && $request->secure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        return $response;
    }

    private function ehPlayerPublico(Request $request): bool
    {
        return $request->is('assistir/*') || $request->is('eduq/*') || $request->is('capa/*');
    }

    /**
     * Em produção vídeo e capa apontam para URL temporária do objeto (outra origem).
     * media-src / img-src só 'self' deixa a página 200 e a mídia muda.
     *
     * @return list<string>
     */
    private function origensDoObjetoDePlay(): array
    {
        $origens = [];
        $disco = (string) config('biblioteca.disk_aulas', 'aulas');
        $cfg = config('filesystems.disks.'.$disco, []);

        if (! is_array($cfg)) {
            return $origens;
        }

        foreach (['endpoint', 'url'] as $campo) {
            $host = parse_url((string) ($cfg[$campo] ?? ''), PHP_URL_HOST);
            if (! is_string($host) || $host === '' || preg_match('/^[A-Za-z0-9.-]+$/', $host) !== 1) {
                continue;
            }

            $origem = 'https://'.$host;
            if (! in_array($origem, $origens, true)) {
                $origens[] = $origem;
            }
        }

        return $origens;
    }
}
