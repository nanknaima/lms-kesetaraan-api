<?php

use Illuminate\Support\Facades\Facade;

return [

    'name' => env('APP_NAME', 'Laravel'),

    'env' => env('APP_ENV', 'production'),

    'debug' => (bool) env('APP_DEBUG', false),

    'url' => env('APP_URL', 'http://localhost'),

    // URL frontend Next.js — dipakai untuk redirect setelah login Google & link di email.
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:3000'),

    // PKBM PPI Taiwan — sekolah & seluruh penggunanya ada di Taiwan (WITA+1/UTC+8),
    // BUKAN Indonesia (WIB/UTC+7) meski kurikulumnya Indonesia. Salah pakai Asia/Jakarta
    // di sini bikin now() selalu terhitung 1 jam LEBIH LAMBAT dari jam dinding Taiwan
    // yang sebenarnya — akibatnya jendela join pertemuan online baru "aktif" 1 jam
    // lebih telat dari jadwal yang diinput guru (guru lihat jam sendiri 22:39, ketik
    // 22:39, tapi now() baru mencapai "22:39" versi Jakarta saat jam dinding Taiwan
    // sudah 23:39). Bug ini ditemukan lewat laporan tombol "Belum Aktif" yang tidak
    // kunjung aktif walau sudah lewat jadwalnya menurut jam guru sendiri.
    'timezone' => env('APP_TIMEZONE', 'Asia/Taipei'),

    'locale' => env('APP_LOCALE', 'id'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'id_ID'),

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

];
