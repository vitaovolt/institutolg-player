<?php

namespace App\Services;

use App\Contracts\AssinadorDeUploadDireto;
use App\Models\Aula;
use Aws\S3\S3Client;
use RuntimeException;

class AssinadorS3DeUploadDireto implements AssinadorDeUploadDireto
{
    public function iniciar(Aula $aula): string
    {
        $cfg = $this->configDisco();
        $res = $this->cliente()->createMultipartUpload([
            'Bucket' => $cfg['bucket'],
            'Key' => $aula->chave_arquivo,
            'ContentType' => 'video/mp4',
        ]);

        $id = (string) ($res['UploadId'] ?? '');

        if ($id === '') {
            throw new RuntimeException('Não foi possível abrir o envio direto do arquivo.');
        }

        return $id;
    }

    public function urlDaParte(Aula $aula, int $parte): string
    {
        $cfg = $this->configDisco();
        $cmd = $this->cliente()->getCommand('UploadPart', [
            'Bucket' => $cfg['bucket'],
            'Key' => $aula->chave_arquivo,
            'UploadId' => $aula->s3_upload_id,
            'PartNumber' => $parte,
        ]);
        $request = $this->cliente()->createPresignedRequest($cmd, '+6 hours');

        return (string) $request->getUri();
    }

    public function completar(Aula $aula, array $partes): void
    {
        $cfg = $this->configDisco();
        $parts = [];

        usort($partes, fn (array $a, array $b): int => $a['part_number'] <=> $b['part_number']);

        foreach ($partes as $parte) {
            $etag = trim((string) $parte['etag'], " \t\n\r\0\x0B\"");
            $parts[] = [
                'ETag' => '"'.$etag.'"',
                'PartNumber' => (int) $parte['part_number'],
            ];
        }

        $this->cliente()->completeMultipartUpload([
            'Bucket' => $cfg['bucket'],
            'Key' => $aula->chave_arquivo,
            'UploadId' => $aula->s3_upload_id,
            'MultipartUpload' => ['Parts' => $parts],
        ]);
    }

    public function abortar(Aula $aula): void
    {
        if (blank($aula->s3_upload_id) || blank($aula->chave_arquivo)) {
            return;
        }

        try {
            $cfg = $this->configDisco();
            $this->cliente()->abortMultipartUpload([
                'Bucket' => $cfg['bucket'],
                'Key' => $aula->chave_arquivo,
                'UploadId' => $aula->s3_upload_id,
                '@http' => [
                    'timeout' => 8,
                    'connect_timeout' => 3,
                ],
            ]);
        } catch (\Throwable) {
            // envio já fechado ou objeto já no destino
        }
    }

    private function cliente(): S3Client
    {
        $cfg = $this->configDisco();

        if (blank($cfg['key']) || blank($cfg['secret']) || blank($cfg['bucket']) || blank($cfg['endpoint'])) {
            throw new RuntimeException('O armazenamento de play ainda não está configurado.');
        }

        return new S3Client([
            'version' => 'latest',
            'region' => $cfg['region'] ?: 'auto',
            'endpoint' => $cfg['endpoint'],
            'use_path_style_endpoint' => (bool) $cfg['use_path_style_endpoint'],
            'credentials' => [
                'key' => $cfg['key'],
                'secret' => $cfg['secret'],
            ],
            'http' => [
                'connect_timeout' => 10,
                'timeout' => 300,
            ],
        ]);
    }

    /**
     * @return array{key:?string,secret:?string,region:?string,bucket:?string,endpoint:?string,use_path_style_endpoint:bool}
     */
    private function configDisco(): array
    {
        $disco = (string) config('biblioteca.disk_aulas', 'aulas');
        $cfg = config('filesystems.disks.'.$disco, []);

        return [
            'key' => $cfg['key'] ?? null,
            'secret' => $cfg['secret'] ?? null,
            'region' => $cfg['region'] ?? 'auto',
            'bucket' => $cfg['bucket'] ?? null,
            'endpoint' => $cfg['endpoint'] ?? null,
            'use_path_style_endpoint' => (bool) ($cfg['use_path_style_endpoint'] ?? true),
        ];
    }
}
