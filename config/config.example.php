<?php
/**
 * Copy this file to config/config.php and adjust the values.
 * Never commit config/config.php to a public repository.
 */
return [
    'app' => [
        'name'      => 'DiarKeep',
        'debug'     => false,
        'timezone'  => 'UTC',
        // Leave null to auto-detect from the front controller path.
        'base_path' => null,
        // Set to true when the app sits behind a reverse proxy / Cloudflare Tunnel.
        'trust_proxy' => true,
        'upload_max'  => 5 * 1024 * 1024,
        'upload_dir'  => dirname(__DIR__) . '/public/uploads',
    ],

    'db' => [
        'dsn'  => 'mysql:host=127.0.0.1;port=3306;dbname=diarkeep;charset=utf8mb4',
        'user' => 'diarkeep',
        'pass' => 'change-me',
    ],
];
