<?php

return [
    'mensalidade_painel' => 287.00,
    'preco_aula_publicada' => 3.80,
    'max_logins_painel' => 5,
    // Export da aula pronta — não o arquivo de edição (~45 GB).
    'upload_max_bytes' => (int) env('BIBLIOTECA_UPLOAD_MAX_BYTES', 2 * 1024 * 1024 * 1024),
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
        'upload_url' => env('BIBLIOTECA_DRIVE_UPLOAD_URL', ''),
        'token' => env('BIBLIOTECA_DRIVE_TOKEN', ''),
        'service_account_path' => env('BIBLIOTECA_DRIVE_SERVICE_ACCOUNT_PATH', ''),
        'folder_id' => env('BIBLIOTECA_DRIVE_FOLDER_ID', ''),
        // Cópia de arquivo grande; 5s do default Educraft é pouco. Produção: 600.
        'timeout' => (int) env('BIBLIOTECA_DRIVE_TIMEOUT', 15),
    ],
];
