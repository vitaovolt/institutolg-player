<?php

return [
    'mensalidade_painel' => 287.00,
    // R$ 3,80 por vídeo com envio concluído (enviado_em), publicada ou não. Capa não entra.
    'preco_aula_publicada' => 3.80,
    // Painel ops (/ops/armazenamento): e-mails Educraft (vírgula). Comparação case-insensitive.
    'ops_emails' => array_values(array_filter(array_map(
        static fn (string $email): string => strtolower(trim($email)),
        explode(',', (string) env('BIBLIOTECA_OPS_EMAILS', 'educraft.ti@gmail.com')),
    ))),
    // Estimativa R2 Standard (só storage; Class A/B fora). Capas fora do cálculo.
    'r2_storage_usd_por_gb' => (float) env('BIBLIOTECA_R2_STORAGE_USD_POR_GB', 0.015),
    'r2_storage_free_gb' => (float) env('BIBLIOTECA_R2_STORAGE_FREE_GB', 10),
    // Export MP4 (não o projeto de edição ~45 GB). 35 GB exige PUT por partes no objeto — não pelo PHP.
    'upload_max_bytes' => (int) env('BIBLIOTECA_UPLOAD_MAX_BYTES', 35 * 1024 * 1024 * 1024),
    'upload_part_bytes' => (int) env('BIBLIOTECA_UPLOAD_PART_BYTES', 100 * 1024 * 1024),
    'upload_extensoes' => ['mp4'],
    'upload_mimes' => ['video/mp4'],
    'disk_aulas' => env('BIBLIOTECA_DISK_AULAS', 'aulas'),
    'disk_drive' => env('BIBLIOTECA_DISK_DRIVE', 'drive'),
    'queue_preparo' => env('BIBLIOTECA_QUEUE_PREPARO', 'biblioteca'),
    // Aula gravada pode passar de 1 h; a URL da mídia expira, o HTML do iframe não.
    'play_ttl_minutos' => (int) env('BIBLIOTECA_PLAY_TTL_MINUTOS', 360),
    'capa_max_bytes' => (int) env('BIBLIOTECA_CAPA_MAX_BYTES', 2 * 1024 * 1024),
    'drive' => [
        'fake' => filter_var(env('BIBLIOTECA_DRIVE_FAKE', true), FILTER_VALIDATE_BOOLEAN),
        // Legado do sync automático. A cópia só dispara no botão; este flag não bloqueia o botão.
        'pausado' => filter_var(env('BIBLIOTECA_DRIVE_PAUSADO', false), FILTER_VALIDATE_BOOLEAN),
        'upload_url' => env('BIBLIOTECA_DRIVE_UPLOAD_URL', ''),
        'token' => env('BIBLIOTECA_DRIVE_TOKEN', ''),
        'service_account_path' => env('BIBLIOTECA_DRIVE_SERVICE_ACCOUNT_PATH', ''),
        'folder_id' => env('BIBLIOTECA_DRIVE_FOLDER_ID', ''),
        // Cópia de arquivo grande; 5s do default Educraft é pouco. Produção: 600.
        'timeout' => (int) env('BIBLIOTECA_DRIVE_TIMEOUT', 600),
    ],
];
