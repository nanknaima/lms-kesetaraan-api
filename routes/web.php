<?php

use Illuminate\Support\Facades\Route;

// Backend ini adalah REST API untuk Web (Next.js) & Mobile (Flutter) — lihat routes/api.php.
// Route ini hanya penanda bahwa server hidup, bukan halaman untuk pengguna akhir.
Route::get('/', function () {
    return response()->json([
        'app' => config('app.name'),
        'status' => 'ok',
        'api' => url('/api'),
    ]);
});
