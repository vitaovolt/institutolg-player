<?php

namespace App\Services\Integrations;

use App\Models\Aula;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ClientePastaDrive
{
    public function enviarCopia(Aula $aula, string $conteudo): string
    {
        if (config('biblioteca.drive.fake')) {
            $path = 'copias/'.$aula->id.'/'.$aula->token_publico.'.mp4';
            Storage::disk((string) config('biblioteca.disk_drive'))->put($path, $conteudo);

            return $path;
        }

        if ($this->usaContaDeServico()) {
            return $this->enviarPelaContaDeServico($aula, $conteudo);
        }

        return $this->enviarPorHttpGenerico($aula, $conteudo);
    }

    private function usaContaDeServico(): bool
    {
        return filled(config('biblioteca.drive.service_account_path'))
            && filled(config('biblioteca.drive.folder_id'));
    }

    private function enviarPorHttpGenerico(Aula $aula, string $conteudo): string
    {
        $url = (string) config('biblioteca.drive.upload_url');

        if ($url === '') {
            throw new RuntimeException('A pasta compartilhada ainda não está configurada.');
        }

        // POST mutável: sem retry (não é idempotente). Timeout obrigatório.
        $response = Http::timeout((int) config('biblioteca.drive.timeout', 15))
            ->withToken((string) config('biblioteca.drive.token'))
            ->attach('file', $conteudo, $aula->titulo.'.mp4')
            ->post($url)
            ->throw();

        return (string) ($response->json('id') ?? $response->json('fileId') ?? 'ok');
    }

    private function enviarPelaContaDeServico(Aula $aula, string $conteudo): string
    {
        $folderId = (string) config('biblioteca.drive.folder_id');
        $token = $this->tokenDaContaDeServico();
        $boundary = 'educraft_'.bin2hex(random_bytes(8));
        $metadata = json_encode([
            'name' => $aula->titulo.'.mp4',
            'parents' => [$folderId],
        ], JSON_UNESCAPED_UNICODE);

        $body = "--{$boundary}\r\n"
            ."Content-Type: application/json; charset=UTF-8\r\n\r\n"
            .$metadata."\r\n"
            ."--{$boundary}\r\n"
            ."Content-Type: video/mp4\r\n\r\n"
            .$conteudo."\r\n"
            ."--{$boundary}--";

        $response = Http::timeout((int) config('biblioteca.drive.timeout', 15))
            ->withToken($token)
            ->withBody($body, 'multipart/related; boundary='.$boundary)
            ->post('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&supportsAllDrives=true')
            ->throw();

        return (string) ($response->json('id') ?? 'ok');
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

    private function base64Url(string $valor): string
    {
        return rtrim(strtr(base64_encode($valor), '+/', '-_'), '=');
    }
}
