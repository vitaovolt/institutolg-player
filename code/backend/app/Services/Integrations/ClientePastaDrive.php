<?php

namespace App\Services\Integrations;

use App\Models\Aula;
use App\Support\CaminhoDaBiblioteca;
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

    private function usaContaDeServico(): bool
    {
        return filled(config('biblioteca.drive.service_account_path'))
            && filled(config('biblioteca.drive.folder_id'));
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
        $curso = $this->garantirPasta($token, $raiz, (string) ($aula->disciplina?->turma?->curso?->nome ?: 'Curso'));
        $turma = $this->garantirPasta($token, $curso, (string) ($aula->disciplina?->turma?->nome ?: 'Turma'));

        return $this->garantirPasta($token, $turma, (string) ($aula->disciplina?->nome ?: 'Disciplina'));
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
