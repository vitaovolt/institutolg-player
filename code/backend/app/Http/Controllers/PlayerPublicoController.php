<?php

namespace App\Http\Controllers;

use App\Models\Aula;
use App\Support\ValidarFotoCapa;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class PlayerPublicoController extends Controller
{
    public function pagina(Aula $aula): Response
    {
        if (! $aula->estaDisponivelParaAluno()) {
            return response()->view('player.indisponivel', [], 404);
        }

        $ttl = max(15, (int) config('biblioteca.play_ttl_minutos', 360));
        $urlMidia = $this->urlTemporariaDaMidia($aula, $ttl);

        $aula->loadMissing(['disciplina.turma.curso']);

        return response()->view('player.assistir', [
            'aula' => $aula,
            'urlMidia' => $urlMidia,
            'urlCapa' => $aula->urlCapa(),
        ]);
    }

    public function midia(Aula $aula): Response
    {
        if (! $aula->estaDisponivelParaAluno()) {
            abort(404);
        }

        $disk = Storage::disk((string) config('biblioteca.disk_aulas'));

        if (! $aula->chave_play || ! $disk->exists($aula->chave_play)) {
            abort(404);
        }

        return $disk->response($aula->chave_play, 'aula.mp4', [
            'Content-Type' => 'video/mp4',
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    public function eduq(Aula $aula): Response
    {
        if (! $aula->estaDisponivelParaAluno()) {
            return response()->view('player.indisponivel', [], 404);
        }

        $aula->loadMissing(['disciplina.turma.curso']);

        return response()->view('player.eduq', [
            'aula' => $aula,
            'srcPlayer' => url('/assistir/'.$aula->token_publico),
        ]);
    }

    public function capa(Aula $aula): Response
    {
        $disk = Storage::disk((string) config('biblioteca.disk_aulas'));

        if (! $aula->chave_capa || ! $disk->exists($aula->chave_capa)) {
            abort(404);
        }

        $binario = $disk->get($aula->chave_capa);
        $mime = ValidarFotoCapa::mime($binario) ?? 'image/jpeg';

        return $disk->response($aula->chave_capa, 'capa.'.$aula->id, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, max-age=300',
        ], 'inline');
    }

    /**
     * Disco de objeto (produção) assina a URL na origem do play.
     * Disco local (dev/teste) continua na rota Laravel `/assistir/.../midia`.
     */
    private function urlTemporariaDaMidia(Aula $aula, int $ttlMinutos): string
    {
        $nome = (string) config('biblioteca.disk_aulas');
        $driver = config("filesystems.disks.{$nome}.driver");

        if ($aula->chave_play && $driver === 's3') {
            return Storage::disk($nome)->temporaryUrl(
                $aula->chave_play,
                now()->addMinutes($ttlMinutos),
            );
        }

        return URL::temporarySignedRoute(
            'player.midia',
            now()->addMinutes($ttlMinutos),
            ['aula' => $aula->token_publico],
            absolute: false,
        );
    }
}
