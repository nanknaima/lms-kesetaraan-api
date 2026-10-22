<?php

return [

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // Firebase Cloud Messaging — push notification (dipakai NotifikasiService)
    'fcm' => [
        'server_key' => env('FCM_SERVER_KEY'),
        'project_id' => env('FCM_PROJECT_ID'),
    ],

    // Google OAuth — tombol "Login dengan Google" untuk user lama (murid/guru/admin
    // yang emailnya sudah terdaftar). Isi GOOGLE_CLIENT_ID/SECRET di .env — lihat
    // instruksi setup di pesan pengiriman patch untuk cara membuatnya di Google Cloud Console.
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

];
