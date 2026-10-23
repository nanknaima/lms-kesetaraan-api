<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        'https://lms-kesetaraan-web.vercel.app',
    ],
    'allowed_origins_patterns' => [
        '#^https://lms-kesetaraan(?:-[a-z0-9-]+)*\.vercel\.app$#',
    ],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];