<?php

namespace App\Services\Integrations;

use App\Models\Aula;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\Turma;
use App\Support\CaminhoDaBiblioteca;
use App\Support\MoverArquivoDaBiblioteca;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ClientePastaDrive
{
    /**
     * @param  resource|string  $conteudo
     */
    public function enviarCopia(Aula $aula, mixed $conteudo, ?int $tamanho = null, string $tipo = 'video', string $extensao = 'mp4'): string
    {
        $aula->loadMissing('disciplina.turma.curso');
        $stream = $this->comoStream($conteudo);
        $criouStream = ! is_resource($conteudo);

        try {
            if (config('biblioteca.drive.fake')) {
                $path = $tipo === 'capa'
                    ? CaminhoDaBiblioteca::chaveCapa($aula, $extensao)
                    : CaminhoDaBiblioteca::chaveVideo($aula->disciplina, (string) $aula->titulo);
                Storage::disk((string) config('biblioteca.disk_drive'))->writeStream($path, $stream);

                return $path;
            }

            if ($this->usaContaDeServico()) {
                return $this->enviarPelaContaDeServico($aula, $stream, $tamanho, $tipo, $extensao);
            }

            return $this->enviarPorHttpGenerico($aula, $stream);
        } finally {
            if ($criouStream && is_resource($stream)) {
                fclose($stream);
            } elseif (! $criouStream && is_resource($conteudo)) {
                fclose($conteudo);
            }
        }
    }

    /**
     * Cria/atualiza pastas (curso → turma → disciplina) e o arquivo da aula.
     * Com id salvo: só renomeia/move. Sem id: faz upload por stream.
     *
     * @param  resource|null  $streamVideo
     * @param  resource|null  $streamCapa
     */
    public function sincronizarAula(
        Aula $aula,
        mixed $streamVideo = null,
        ?int $tamanhoVideo = null,
        mixed $streamCapa = null,
        ?int $tamanhoCapa = null,
        string $extCapa = 'jpg',
    ): void {
        $aula->loadMissing('disciplina.turma.curso');

        try {
            if (config('biblioteca.drive.fake')) {
                $this->sincronizarNoDiscoFake($aula, $streamVideo, $streamCapa, $extCapa);

                return;
            }

            if ($this->usaContaDeServico()) {
                $token = $this->tokenDaContaDeServico();
                $pastaId = $this->garantirArvore($aula, $token);
                $this->sincronizarArquivoNaConta($aula, $token, $pastaId, $streamVideo, $tamanhoVideo, 'video', 'mp4');
                if (filled($aula->chave_capa) && ($streamCapa !== null || filled($aula->drive_capa_file_id))) {
                    $this->sincronizarArquivoNaConta($aula, $token, $pastaId, $streamCapa, $tamanhoCapa, 'capa', $extCapa);
                }

                return;
            }

            if ($streamVideo !== null) {
                $this->enviarPorHttpGenerico($aula, $this->comoStream($streamVideo));
            }
        } finally {
            foreach ([$streamVideo, $streamCapa] as $stream) {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        }
    }

    public function copiaAtiva(): bool
    {
        if (config('biblioteca.drive.fake')) {
            return true;
        }

        if ($this->usaContaDeServico()) {
            return true;
        }

        return filled(config('biblioteca.drive.upload_url'));
    }

    public function renomearCopia(Aula $aula, string $tituloAnterior): void
    {
        if ((string) $aula->titulo === $tituloAnterior) {
            return;
        }

        $aula->loadMissing('disciplina.turma.curso');

        if (config('biblioteca.drive.fake')) {
            $this->moverNoDiscoDrive($aula, $tituloAnterior);

            return;
        }

        if ($this->usaContaDeServico()) {
            $this->renomearNaContaDeServico($aula, $tituloAnterior);
        }
    }

    private function usaContaDeServico(): bool
    {
        return filled(config('biblioteca.drive.service_account_path'))
            && filled(config('biblioteca.drive.folder_id'));
    }

    private function moverNoDiscoDrive(Aula $aula, string $tituloAnterior): void
    {
        $disk = Storage::disk((string) config('biblioteca.disk_drive'));
        $disciplina = $aula->disciplina;
        $pares = [
            CaminhoDaBiblioteca::chaveVideo($disciplina, $tituloAnterior) => CaminhoDaBiblioteca::chaveVideo($disciplina, (string) $aula->titulo),
        ];
        if (filled($aula->chave_capa)) {
            $ext = pathinfo((string) $aula->chave_capa, PATHINFO_EXTENSION) ?: 'jpg';
            $pares[CaminhoDaBiblioteca::chaveCapaPara($disciplina, $tituloAnterior, $ext)]
                = CaminhoDaBiblioteca::chaveCapaPara($disciplina, (string) $aula->titulo, $ext);
        }

        foreach ($pares as $de => $para) {
            MoverArquivoDaBiblioteca::seExistir($disk, $de, $para);
        }
    }

    private function renomearNaContaDeServico(Aula $aula, string $tituloAnterior): void
    {
        $token = $this->tokenDaContaDeServico();
        $pastaId = $this->garantirArvore($aula, $token);

        $this->renomearArquivoNaPasta(
            $token,
            $pastaId,
            CaminhoDaBiblioteca::nomeArquivoDrivePara($tituloAnterior, 'video'),
            CaminhoDaBiblioteca::nomeArquivoDrive($aula, 'video'),
        );

        if (! filled($aula->chave_capa)) {
            return;
        }

        $ext = pathinfo((string) $aula->chave_capa, PATHINFO_EXTENSION) ?: 'jpg';
        $this->renomearArquivoNaPasta(
            $token,
            $pastaId,
            CaminhoDaBiblioteca::nomeArquivoDrivePara($tituloAnterior, 'capa', $ext),
            CaminhoDaBiblioteca::nomeArquivoDrive($aula, 'capa', $ext),
        );
    }

    private function renomearArquivoNaPasta(string $token, string $pastaId, string $nomeAntigo, string $nomeNovo): void
    {
        if ($nomeAntigo === $nomeNovo) {
            return;
        }

        $id = $this->encontrarArquivoNaPasta($token, $pastaId, $nomeAntigo);
        if ($id === '') {
            return;
        }

        Http::timeout((int) config('biblioteca.drive.timeout', 15))
            ->withToken($token)
            ->patch('https://www.googleapis.com/drive/v3/files/'.$id.'?supportsAllDrives=true', [
                'name' => $nomeNovo,
            ])
            ->throw();
    }

    private function encontrarArquivoNaPasta(string $token, string $pastaId, string $nome): string
    {
        $q = "name='".$this->escapeDrive($nome)."' and '".$this->escapeDrive($pastaId)."' in parents and trashed=false";

        $lista = Http::timeout(15)
            ->retry(3, 200)
            ->withToken($token)
            ->get('https://www.googleapis.com/drive/v3/files', [
                'q' => $q,
                'pageSize' => 1,
                'fields' => 'files(id,name)',
                'supportsAllDrives' => 'true',
                'includeItemsFromAllDrives' => 'true',
                'corpora' => 'allDrives',
            ])
            ->throw();

        return (string) data_get($lista->json(), 'files.0.id');
    }

    /**
     * @param  resource  $stream
     */
    private function enviarPorHttpGenerico(Aula $aula, $stream): string
    {
        $url = (string) config('biblioteca.drive.upload_url');

        if ($url === '') {
            throw new RuntimeException('A pasta compartilhada ainda não está configurada.');
        }

        $binario = stream_get_contents($stream);
        if ($binario === false) {
            $binario = '';
        }

        $response = Http::timeout((int) config('biblioteca.drive.timeout', 15))
            ->withToken((string) config('biblioteca.drive.token'))
            ->attach('file', $binario, $aula->titulo.'.mp4')
            ->post($url)
            ->throw();

        return (string) ($response->json('id') ?? $response->json('fileId') ?? 'ok');
    }

    /**
     * @param  resource  $stream
     */
    private function enviarPelaContaDeServico(Aula $aula, $stream, ?int $tamanho, string $tipo, string $extensao): string
    {
        $token = $this->tokenDaContaDeServico();
        $pastaId = $this->garantirArvore($aula, $token);
        $mime = $tipo === 'capa' ? $this->mimeCapa($extensao) : 'video/mp4';
        $metadata = json_encode([
            'name' => CaminhoDaBiblioteca::nomeArquivoDrive($aula, $tipo, $extensao),
            'parents' => [$pastaId],
        ], JSON_UNESCAPED_UNICODE);

        if ($tamanho === null) {
            $stat = fstat($stream);
            $tamanho = is_array($stat) ? (int) $stat['size'] : 0;
            rewind($stream);
        }

        $iniciar = Http::timeout(15)
            ->withToken($token)
            ->withHeaders([
                'Content-Type' => 'application/json; charset=UTF-8',
                'X-Upload-Content-Type' => $mime,
                'X-Upload-Content-Length' => (string) $tamanho,
            ])
            ->withBody($metadata, 'application/json; charset=UTF-8')
            ->post('https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable&supportsAllDrives=true')
            ->throw();

        $session = (string) $iniciar->header('Location');

        if ($session === '') {
            throw new RuntimeException('A pasta compartilhada não abriu o envio da cópia.');
        }

        $chunk = 8 * 1024 * 1024;
        $offset = 0;
        $id = 'ok';
        $timeout = max(60, (int) config('biblioteca.drive.timeout', 15));

        while (! feof($stream)) {
            $bloco = fread($stream, $chunk);
            if ($bloco === false || $bloco === '') {
                break;
            }
            $len = strlen($bloco);
            $fim = $offset + $len - 1;
            $resposta = Http::timeout($timeout)
                ->withOptions(['allow_redirects' => false])
                ->withBody($bloco, $mime)
                ->withHeaders([
                    'Content-Range' => 'bytes '.$offset.'-'.$fim.'/'.$tamanho,
                    'Content-Length' => (string) $len,
                ])
                ->put($session);

            if ($resposta->status() !== 308) {
                $resposta->throw();
                $id = (string) ($resposta->json('id') ?? $id);
            }
            $offset += $len;
        }

        return $id === '' ? 'ok' : $id;
    }

    private function garantirArvore(Aula $aula, string $token): string
    {
        $aula->loadMissing('disciplina.turma.curso');
        $raiz = (string) config('biblioteca.drive.folder_id');
        $curso = $aula->disciplina->turma->curso;
        $turma = $aula->disciplina->turma;
        $disciplina = $aula->disciplina;
        $cursoId = $this->alinharPasta($token, $curso, $raiz, (string) ($curso->nome ?: 'Curso'));
        $turmaId = $this->alinharPasta($token, $turma, $cursoId, (string) ($turma->nome ?: 'Turma'));

        return $this->alinharPasta($token, $disciplina, $turmaId, (string) ($disciplina->nome ?: 'Disciplina'));
    }

    private function alinharPasta(string $token, Curso|Turma|Disciplina $model, string $pai, string $nome): string
    {
        $nome = trim($nome) !== '' ? trim($nome) : 'Sem nome';
        $salvo = (string) ($model->drive_folder_id ?? '');

        if ($salvo !== '') {
            $meta = $this->obterArquivo($token, $salvo);
            if ($meta !== null && empty($meta['trashed'])) {
                $this->alinharNomeEPai($token, $salvo, $meta, $nome, $pai);

                return $salvo;
            }
        }

        $id = $this->garantirPasta($token, $pai, $nome);
        $this->persistirFolderId($model, $id);

        return $id;
    }

    /**
     * @return array{id?: string, name?: string, parents?: list<string>, trashed?: bool}|null
     */
    private function obterArquivo(string $token, string $id): ?array
    {
        $resposta = Http::timeout(15)
            ->retry(3, 200)
            ->withToken($token)
            ->get('https://www.googleapis.com/drive/v3/files/'.$id, [
                'fields' => 'id,name,parents,trashed',
                'supportsAllDrives' => 'true',
            ]);

        if ($resposta->status() === 404) {
            return null;
        }

        $resposta->throw();
        $json = $resposta->json();
        if (! is_array($json) || empty($json['id'])) {
            return null;
        }

        return $json;
    }

    /**
     * @param  array{id?: string, name?: string, parents?: list<string>}  $meta
     */
    private function alinharNomeEPai(string $token, string $id, array $meta, string $nome, string $pai): void
    {
        $query = 'supportsAllDrives=true';
        $parents = $meta['parents'] ?? [];
        if (! in_array($pai, $parents, true)) {
            $query .= '&addParents='.rawurlencode($pai);
            $sair = array_values(array_filter($parents, fn ($p) => $p !== $pai));
            if ($sair !== []) {
                $query .= '&removeParents='.rawurlencode(implode(',', $sair));
            }
        }

        $body = [];
        if (($meta['name'] ?? '') !== $nome) {
            $body['name'] = $nome;
        }

        if ($body === [] && ! str_contains($query, 'addParents')) {
            return;
        }

        Http::timeout((int) config('biblioteca.drive.timeout', 15))
            ->withToken($token)
            ->patch('https://www.googleapis.com/drive/v3/files/'.$id.'?'.$query, $body === [] ? new \stdClass : $body)
            ->throw();
    }

    private function sincronizarNoDiscoFake(Aula $aula, mixed $streamVideo, mixed $streamCapa, string $extCapa): void
    {
        $curso = $aula->disciplina->turma->curso;
        $turma = $aula->disciplina->turma;
        $disciplina = $aula->disciplina;
        $cursoId = CaminhoDaBiblioteca::segmento((string) $curso->nome);
        $turmaId = $cursoId.'/'.CaminhoDaBiblioteca::segmento((string) $turma->nome);
        $discId = $turmaId.'/'.CaminhoDaBiblioteca::segmento((string) $disciplina->nome);
        $this->persistirFolderId($curso, $cursoId);
        $this->persistirFolderId($turma, $turmaId);
        $this->persistirFolderId($disciplina, $discId);

        $this->sincronizarArquivoFake($aula, 'video', 'mp4', $streamVideo);
        if (filled($aula->chave_capa) || $streamCapa !== null) {
            $this->sincronizarArquivoFake($aula, 'capa', $extCapa, $streamCapa);
        }
    }

    private function sincronizarArquivoFake(Aula $aula, string $tipo, string $extensao, mixed $stream): void
    {
        $path = $tipo === 'capa'
            ? CaminhoDaBiblioteca::chaveCapa($aula, $extensao)
            : CaminhoDaBiblioteca::chaveVideo($aula->disciplina, (string) $aula->titulo);
        $campo = $tipo === 'capa' ? 'drive_capa_file_id' : 'drive_file_id';
        $antigo = (string) ($aula->{$campo} ?? '');
        $disk = Storage::disk((string) config('biblioteca.disk_drive'));

        if (is_resource($stream)) {
            $disk->writeStream($path, $stream);
        } elseif ($antigo !== '' && $antigo !== $path) {
            MoverArquivoDaBiblioteca::seExistir($disk, $antigo, $path);
        }

        $this->persistirFolderId($aula, $path, $campo);
    }

    /**
     * @param  resource|null  $stream
     */
    private function sincronizarArquivoNaConta(
        Aula $aula,
        string $token,
        string $pastaId,
        mixed $stream,
        ?int $tamanho,
        string $tipo,
        string $extensao,
    ): void {
        $campo = $tipo === 'capa' ? 'drive_capa_file_id' : 'drive_file_id';
        $salvo = (string) ($aula->{$campo} ?? '');
        $nome = CaminhoDaBiblioteca::nomeArquivoDrive($aula, $tipo, $extensao);

        if ($salvo !== '' && $stream === null) {
            $meta = $this->obterArquivo($token, $salvo);
            if ($meta !== null && empty($meta['trashed'])) {
                $this->alinharNomeEPai($token, $salvo, $meta, $nome, $pastaId);

                return;
            }
        }

        if ($stream === null) {
            throw new RuntimeException('Não foi possível localizar o arquivo na pasta compartilhada.');
        }

        $id = $this->enviarPelaContaDeServico($aula, $this->comoStream($stream), $tamanho, $tipo, $extensao);
        if ($id !== '' && $id !== 'ok') {
            $this->persistirFolderId($aula, $id, $campo);
        }
    }

    private function persistirFolderId(Model $model, string $id, string $campo = 'drive_folder_id'): void
    {
        if ($id === '' || (string) ($model->{$campo} ?? '') === $id) {
            return;
        }

        $model->forceFill([$campo => $id])->save();
    }

    private function garantirPasta(string $token, string $pai, string $nome): string
    {
        $nome = trim($nome) !== '' ? trim($nome) : 'Sem nome';
        $q = "mimeType='application/vnd.google-apps.folder' and name='".$this->escapeDrive($nome)."' and '".$this->escapeDrive($pai)."' in parents and trashed=false";

        $lista = Http::timeout(15)
            ->retry(3, 200)
            ->withToken($token)
            ->get('https://www.googleapis.com/drive/v3/files', [
                'q' => $q,
                'pageSize' => 1,
                'fields' => 'files(id,name)',
                'supportsAllDrives' => 'true',
                'includeItemsFromAllDrives' => 'true',
                'corpora' => 'allDrives',
            ])
            ->throw();

        $id = (string) data_get($lista->json(), 'files.0.id');
        if ($id !== '') {
            return $id;
        }

        $criar = Http::timeout(15)
            ->withToken($token)
            ->post('https://www.googleapis.com/drive/v3/files?supportsAllDrives=true', [
                'name' => $nome,
                'mimeType' => 'application/vnd.google-apps.folder',
                'parents' => [$pai],
            ])
            ->throw();

        $id = (string) $criar->json('id');
        if ($id === '') {
            throw new RuntimeException('Não foi possível criar a pasta na cópia compartilhada.');
        }

        return $id;
    }

    private function escapeDrive(string $valor): string
    {
        return str_replace("'", "\\'", $valor);
    }

    private function mimeCapa(string $extensao): string
    {
        return match (strtolower(ltrim($extensao, '.'))) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
    }

    private function tokenDaContaDeServico(): string
    {
        $path = (string) config('biblioteca.drive.service_account_path');

        if (! is_readable($path)) {
            throw new RuntimeException('A conta da pasta compartilhada não está no servidor.');
        }

        $cred = json_decode((string) file_get_contents($path), true);

        if (! is_array($cred) || empty($cred['client_email']) || empty($cred['private_key'])) {
            throw new RuntimeException('O arquivo da conta da pasta compartilhada está incompleto.');
        }

        $now = time();
        $header = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $payload = $this->base64Url(json_encode([
            'iss' => $cred['client_email'],
            'scope' => 'https://www.googleapis.com/auth/drive',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => $now + 3600,
            'iat' => $now,
        ]));
        $unsigned = $header.'.'.$payload;

        $ok = openssl_sign($unsigned, $assinatura, $cred['private_key'], OPENSSL_ALGO_SHA256);

        if (! $ok) {
            throw new RuntimeException('Não foi possível assinar o acesso à pasta compartilhada.');
        }

        $jwt = $unsigned.'.'.$this->base64Url($assinatura);

        $response = Http::timeout(5)
            ->retry(3, 200)
            ->asForm()
            ->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ])
            ->throw();

        $token = (string) $response->json('access_token');

        if ($token === '') {
            throw new RuntimeException('A pasta compartilhada não devolveu autorização.');
        }

        return $token;
    }

    /**
     * @param  resource|string  $conteudo
     * @return resource
     */
    private function comoStream(mixed $conteudo)
    {
        if (is_resource($conteudo)) {
            return $conteudo;
        }

        $h = fopen('php://temp', 'w+b');
        fwrite($h, (string) $conteudo);
        rewind($h);

        return $h;
    }

    private function base64Url(string $valor): string
    {
        return rtrim(strtr(base64_encode($valor), '+/', '-_'), '=');
    }
}
