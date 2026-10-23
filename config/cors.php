<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    
    // Kita tulis langsung alamat frontend Vercel Anda di sini
    'allowed_origins' => [
        'https://lms-kesetaraan-web.vercel.app',
    ],
    
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];