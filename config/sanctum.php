<?php

use Laravel\Sanctum\Sanctum;

return [
    // Domain web yang boleh pakai cookie-based auth (Next.js dev/prod origin)
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', 'localhost:3000')),

    'guard' => ['web'],

    // Mobile (Flutter) TIDAK stateful — selalu pakai Bearer token (personal access token)
    'expiration' => null, // masa berlaku diatur per-token saat createToken() (lihat LoginController)

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],
];
