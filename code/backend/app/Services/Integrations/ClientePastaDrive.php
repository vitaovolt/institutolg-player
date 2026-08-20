<?php

namespace App\Services\Integrations;

use App\Models\Aula;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\Turma;
use App\Support\CaminhoDaBiblioteca;
use App\Support\LerBlocoDoStream;
use App\Support\MoverArquivoDaBiblioteca;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

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

    public function podeListarPasta(): bool
    {
        return (bool) config('biblioteca.drive.fake') || $this->usaContaDeServico();
    }

    /**
     * @return list<array{id: string, name: string, mimeType: string, size: int}>
     */
    public function listarFilhos(string $paiId): array
    {
        if (config('biblioteca.drive.fake')) {
            return $this->listarFilhosFake($paiId);
        }

        if (! $this->usaContaDeServico()) {
            throw new RuntimeException('A pasta compartilhada ainda não está configurada para importar.');
        }

        return $this->listarFilhosNaConta($this->tokenDaContaDeServico(), $paiId);
    }

    /**
     * @return resource
     */
    public function abrirDownload(string $id)
    {
        if (config('biblioteca.drive.fake')) {
            $stream = Storage::disk((string) config('biblioteca.disk_drive'))->readStream($id);
            if ($stream === false || $stream === null) {
                throw new RuntimeException('Não foi possível ler o arquivo na pasta compartilhada.');
            }

            return $stream;
        }

        if (! $this->usaContaDeServico()) {
            throw new RuntimeException('A pasta compartilhada ainda não está configurada para importar.');
        }

        $resposta = Http::timeout(max(60, (int) config('biblioteca.drive.timeout', 15)))
            ->withToken($this->tokenDaContaDeServico())
            ->withOptions(['stream' => true])
            ->get('https://www.googleapis.com/drive/v3/files/'.rawurlencode($id), [
                'alt' => 'media',
                'supportsAllDrives' => 'true',
            ])
            ->throw();

        $corpo = $resposta->toPsrResponse()->getBody();
        $recurso = method_exists($corpo, 'detach') ? $corpo->detach() : null;
        if (! is_resource($recurso)) {
            throw new RuntimeException('Não foi possível abrir o arquivo da pasta compartilhada.');
        }

        return $recurso;
    }

    public function baixarFaixa(string $id, int $bytes = 32): string
    {
        $bytes = max(12, $bytes);

        if (config('biblioteca.drive.fake')) {
            $disk = Storage::disk((string) config('biblioteca.disk_drive'));
            if (! $disk->exists($id)) {
                throw new RuntimeException('Não foi possível ler o arquivo na pasta compartilhada.');
            }

            return substr((string) $disk->get($id), 0, $bytes);
        }

        if (! $this->usaContaDeServico()) {
            throw new RuntimeException('A pasta compartilhada ainda não está configurada para importar.');
        }

        $resposta = Http::timeout(15)
            ->retry(3, 200)
            ->withToken($this->tokenDaContaDeServico())
            ->withHeaders(['Range' => 'bytes=0-'.($bytes - 1)])
            ->get('https://www.googleapis.com/drive/v3/files/'.rawurlencode($id), [
                'alt' => 'media',
                'supportsAllDrives' => 'true',
            ])
            ->throw();

        $corpo = (string) $resposta->body();
        if ($corpo === '') {
            throw new RuntimeException('Não foi possível ler o início do arquivo na pasta compartilhada.');
        }

        return $corpo;
    }

    /**
     * @return list<array{id: string, name: string, mimeType: string, size: int}>
     */
    private function listarFilhosFake(string $paiId): array
    {
        $disk = Storage::disk((string) config('biblioteca.disk_drive'));
        $pai = trim($paiId, '/');
        $itens = [];

        foreach ($disk->directories($pai) as $pasta) {
            $itens[] = [
                'id' => $pasta,
                'name' => basename(str_replace('\\', '/', $pasta)),
                'mimeType' => 'application/vnd.google-apps.folder',
                'size' => 0,
            ];
        }

        foreach ($disk->files($pai) as $arquivo) {
            $nome = basename(str_replace('\\', '/', $arquivo));
            $ext = strtolower((string) pathinfo($nome, PATHINFO_EXTENSION));
            $mime = match ($ext) {
                'mp4' => 'video/mp4',
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp',
                default => 'application/octet-stream',
            };
            try {
                $tamanho = (int) $disk->size($arquivo);
            } catch (Throwable) {
                $tamanho = 0;
            }
            $itens[] = [
                'id' => $arquivo,
                'name' => $nome,
                'mimeType' => $mime,
                'size' => $tamanho,
            ];
        }

        return $itens;
    }

    /**
     * @return list<array{id: string, name: string, mimeType: string, size: int}>
     */
    private function listarFilhosNaConta(string $token, string $paiId): array
    {
        $itens = [];
        $page = null;

        do {
            $query = [
                'q' => "'".$this->escapeDrive($paiId)."' in parents and trashed=false",
                'pageSize' => 1000,
                'fields' => 'nextPageToken,files(id,name,mimeType,size)',
                'supportsAllDrives' => 'true',
                'includeItemsFromAllDrives' => 'true',
                'corpora' => 'allDrives',
            ];
            if (is_string($page) && $page !== '') {
                $query['pageToken'] = $page;
            }

            $lista = Http::timeout(15)
                ->retry(3, 200)
                ->withToken($token)
                ->get('https://www.googleapis.com/drive/v3/files', $query)
                ->throw()
                ->json();

            foreach ($lista['files'] ?? [] as $arquivo) {
                if (! is_array($arquivo) || empty($arquivo['id'])) {
                    continue;
                }
                $itens[] = [
                    'id' => (string) $arquivo['id'],
                    'name' => (string) ($arquivo['name'] ?? ''),
                    'mimeType' => (string) ($arquivo['mimeType'] ?? ''),
                    'size' => (int) ($arquivo['size'] ?? 0),
                ];
            }

            $page = $lista['nextPageToken'] ?? null;
        } while (is_string($page) && $page !== '');

        return $itens;
    }

    /**
     * Reenvia se a pasta não tiver o arquivo, ou se o tamanho for outro (substituição).
     *
     * @param  'video'|'capa'  $tipo
     */
    public function precisaEnviarCopia(Aula $aula, string $tipo, ?int $tamanhoLocal): bool
    {
        $aula->loadMissing('disciplina.turma.curso');
        $campo = $tipo === 'capa' ? 'drive_capa_file_id' : 'drive_file_id';
        $salvo = (string) ($aula->{$campo} ?? '');

        if ($salvo === '') {
            return true;
        }

        if ($tamanhoLocal === null || $tamanhoLocal < 0) {
            return true;
        }

        if (config('biblioteca.drive.fake')) {
            $disk = Storage::disk((string) config('biblioteca.disk_drive'));
            if (! $disk->exists($salvo)) {
                return true;
            }

            try {
                return (int) $disk->size($salvo) !== $tamanhoLocal;
            } catch (Throwable) {
                return true;
            }
        }

        if (! $this->usaContaDeServico()) {
            return true;
        }

        $meta = $this->obterArquivo($this->tokenDaContaDeServico(), $salvo);
        if ($meta === null || ! empty($meta['trashed'])) {
            return true;
        }

        $tamanhoPasta = (int) ($meta['size'] ?? -1);

        return $tamanhoPasta !== $tamanhoLocal;
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
    private function enviarPelaContaDeServico(
        Aula $aula,
        $stream,
        ?int $tamanho,
        string $tipo,
        string $extensao,
        ?string $fileId = null,
    ): string {
        $token = $this->tokenDaContaDeServico();
        $pastaId = $this->garantirArvore($aula, $token);
        $mime = $tipo === 'capa' ? $this->mimeCapa($extensao) : 'video/mp4';

        if ($tipo === 'capa') {
            return $this->enviarMultipartPequeno($token, $pastaId, $aula, $stream, $mime, $extensao, $fileId);
        }

        $metadata = json_encode($fileId
            ? ['name' => CaminhoDaBiblioteca::nomeArquivoDrive($aula, $tipo, $extensao)]
            : [
                'name' => CaminhoDaBiblioteca::nomeArquivoDrive($aula, $tipo, $extensao),
                'parents' => [$pastaId],
            ], JSON_UNESCAPED_UNICODE);

        if ($tamanho === null) {
            $stat = fstat($stream);
            $tamanho = is_array($stat) ? (int) $stat['size'] : 0;
            rewind($stream);
        }

        $url = $fileId
            ? 'https://www.googleapis.com/upload/drive/v3/files/'.rawurlencode($fileId).'?uploadType=resumable&supportsAllDrives=true'
            : 'https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable&supportsAllDrives=true';

        $iniciar = Http::timeout(15)
            ->withToken($token)
            ->withHeaders([
                'Content-Type' => 'application/json; charset=UTF-8',
                'X-Upload-Content-Type' => $mime,
                'X-Upload-Content-Length' => (string) $tamanho,
            ])
            ->withBody($metadata, 'application/json; charset=UTF-8');

        $iniciar = $fileId ? $iniciar->patch($url) : $iniciar->post($url);
        $iniciar->throw();

        $session = (string) $iniciar->header('Location');

        if ($session === '') {
            throw new RuntimeException('A pasta compartilhada não abriu o envio da cópia.');
        }

        $chunk = 8 * 1024 * 1024;
        $minimo = 256 * 1024;
        $offset = 0;
        $id = 'ok';
        $timeout = max(60, (int) config('biblioteca.drive.timeout', 15));

        while (! feof($stream)) {
            $alvo = $chunk;
            if ($tamanho > 0) {
                $restante = $tamanho - $offset;
                if ($restante <= 0) {
                    break;
                }
                $alvo = (int) min($chunk, $restante);
            }

            $bloco = LerBlocoDoStream::handle($stream, $alvo);
            if ($bloco === '') {
                break;
            }

            $len = strlen($bloco);
            $fim = $offset + $len - 1;
            $ehFinal = $tamanho > 0
                ? ($offset + $len >= $tamanho)
                : feof($stream);

            if (! $ehFinal && ($len < $minimo || $len % $minimo !== 0)) {
                throw new RuntimeException('A leitura do arquivo veio incompleta. Tente de novo.');
            }

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

        return $id === '' ? ($fileId ?: 'ok') : $id;
    }

    /**
     * Capa (até 2 MB): multipart. Resumable exige pacote ≥ 256 KiB se não for o último.
     *
     * @param  resource  $stream
     */
    private function enviarMultipartPequeno(
        string $token,
        string $pastaId,
        Aula $aula,
        $stream,
        string $mime,
        string $extensao,
        ?string $fileId = null,
    ): string {
        $bytes = stream_get_contents($stream);
        if ($bytes === false || $bytes === '') {
            throw new RuntimeException('Não foi possível ler a capa para a pasta compartilhada.');
        }

        $boundary = 'educraft_'.bin2hex(random_bytes(12));
        $meta = json_encode($fileId
            ? ['name' => CaminhoDaBiblioteca::nomeArquivoDrive($aula, 'capa', $extensao)]
            : [
                'name' => CaminhoDaBiblioteca::nomeArquivoDrive($aula, 'capa', $extensao),
                'parents' => [$pastaId],
            ], JSON_UNESCAPED_UNICODE);
        $corpo = '--'.$boundary."\r\n"
            ."Content-Type: application/json; charset=UTF-8\r\n\r\n"
            .$meta."\r\n"
            .'--'.$boundary."\r\n"
            .'Content-Type: '.$mime."\r\n\r\n"
            .$bytes."\r\n"
            .'--'.$boundary.'--';

        $url = $fileId
            ? 'https://www.googleapis.com/upload/drive/v3/files/'.rawurlencode($fileId).'?uploadType=multipart&supportsAllDrives=true'
            : 'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&supportsAllDrives=true';

        $pedido = Http::timeout(max(30, (int) config('biblioteca.drive.timeout', 15)))
            ->withToken($token)
            ->withBody($corpo, 'multipart/related; boundary='.$boundary);

        $resposta = $fileId ? $pedido->patch($url) : $pedido->post($url);
        $resposta->throw();

        $id = (string) ($resposta->json('id') ?? '');

        return $id === '' ? ($fileId ?: 'ok') : $id;
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
                'fields' => 'id,name,parents,trashed,size',
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
        $metaSalvo = $salvo !== '' ? $this->obterArquivo($token, $salvo) : null;
        $arquivoVivo = $metaSalvo !== null && empty($metaSalvo['trashed']);

        if ($stream === null) {
            if ($arquivoVivo) {
                $this->alinharNomeEPai($token, $salvo, $metaSalvo, $nome, $pastaId);

                return;
            }

            throw new RuntimeException('Não foi possível localizar o arquivo na pasta compartilhada.');
        }

        $id = $this->enviarPelaContaDeServico(
            $aula,
            $this->comoStream($stream),
            $tamanho,
            $tipo,
            $extensao,
            $arquivoVivo ? $salvo : null,
        );
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
