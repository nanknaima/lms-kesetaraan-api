<?php

use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\PendaftaranController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\LogoutController;
use App\Http\Controllers\Api\Auth\RefreshTokenController;
use App\Http\Controllers\Api\Guru\ExportController;
use App\Http\Controllers\Api\Guru\PertemuanOnlineController;
use App\Http\Controllers\Api\Guru\TugasController;
use App\Http\Controllers\Api\Murid\PertemuanJoinController;
use App\Http\Controllers\Api\Murid\TugasSubmitController;
use App\Http\Controllers\Api\PengumumanController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — dikonsumsi bersama oleh Web (Next.js) & Mobile (Flutter)
|--------------------------------------------------------------------------
| Daftar lengkap endpoint (termasuk yang belum dikodekan di scaffold ini,
| mis. Kuis/Absensi/NilaiAkhir) ada di docs/api-endpoints.md — controller
| lain mengikuti pola yang sama seperti TugasController & PengumumanController.
*/

// ── Public ───────────────────────────────────────────────────────────
Route::post('/auth/login', LoginController::class);
Route::post('/pendaftaran', [\App\Http\Controllers\Api\PendaftaranPublicController::class, 'store']);

// Login Google — hanya untuk akun yang sudah terdaftar (lihat GoogleAuthController).
Route::get('/auth/google/redirect', [\App\Http\Controllers\Api\Auth\GoogleAuthController::class, 'redirect']);
Route::get('/auth/google/callback', [\App\Http\Controllers\Api\Auth\GoogleAuthController::class, 'callback']);

// Lupa password — kirim & verifikasi OTP lewat email.
Route::post('/auth/forgot-password', [\App\Http\Controllers\Api\Auth\PasswordResetController::class, 'sendOtp']);
Route::post('/auth/verify-otp', [\App\Http\Controllers\Api\Auth\PasswordResetController::class, 'verifyOtp']);
Route::post('/auth/reset-password', [\App\Http\Controllers\Api\Auth\PasswordResetController::class, 'reset']);

// ── Authenticated (semua role) ──────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', LogoutController::class);
    // load(...) aman dipanggil untuk semua role — hasOne cukup mengembalikan null bila tidak ada baris yang cocok (mis. murid tidak punya guruProfile).
    Route::get('/auth/me', fn (\Illuminate\Http\Request $r) => response()->json(['data' => $r->user()->load(['muridProfile.kelas', 'guruProfile.mapel'])]));
    Route::put('/profile', [\App\Http\Controllers\Api\ProfileController::class, 'update']);
    Route::post('/profile/avatar', [\App\Http\Controllers\Api\ProfileController::class, 'updateAvatar']);
    Route::delete('/profile', [\App\Http\Controllers\Api\ProfileController::class, 'destroy']);

    // Refresh token pakai token dengan ability 'refresh' saja (lihat LoginController)
    Route::post('/auth/refresh', RefreshTokenController::class)->middleware('abilities:refresh');

    Route::get('/pengumuman/slideshow', [PengumumanController::class, 'slideshow']);
    Route::get('/pengumuman/{pengumuman}', [PengumumanController::class, 'show']);

    Route::get('/pertemuan-online/upcoming', [PertemuanOnlineController::class, 'upcoming']);
    Route::get('/pertemuan-online/{pertemuan}/join', PertemuanJoinController::class);

    Route::get('/notifikasi', [\App\Http\Controllers\Api\NotifikasiController::class, 'index']);
    Route::post('/notifikasi/{notifikasi}/read', [\App\Http\Controllers\Api\NotifikasiController::class, 'markRead']);

    Route::get('/diskusi', [\App\Http\Controllers\Api\DiskusiController::class, 'index']);
    Route::post('/diskusi', [\App\Http\Controllers\Api\DiskusiController::class, 'store']);
    Route::delete('/diskusi/{diskusi}', [\App\Http\Controllers\Api\DiskusiController::class, 'destroy']);
    Route::post('/diskusi/{diskusi}/reaksi', [\App\Http\Controllers\Api\DiskusiController::class, 'toggleReaksi']);
    Route::post('/materi/{materi}/reaksi', [\App\Http\Controllers\Api\DiskusiController::class, 'toggleReaksiMateri']);

    Route::get('/media-library', [\App\Http\Controllers\Api\MediaLibraryController::class, 'index']);

    // Chat personal (DM) — semua role: murid<->guru, murid<->murid sekelas, guru<->guru.
    Route::get('/chat/kontak', [\App\Http\Controllers\Api\ChatController::class, 'kontak']);
    Route::get('/chat/{lawan}', [\App\Http\Controllers\Api\ChatController::class, 'pesan']);
    Route::post('/chat/{lawan}', [\App\Http\Controllers\Api\ChatController::class, 'kirim']);
    Route::post('/chat/{lawan}/pesan/{pesan}/pin', [\App\Http\Controllers\Api\ChatController::class, 'togglePin']);
    Route::post('/chat/{lawan}/hapus', [\App\Http\Controllers\Api\ChatController::class, 'hapusPesan']);
    Route::post('/chat/{lawan}/hapus-semua', [\App\Http\Controllers\Api\ChatController::class, 'hapusSemuaPesan']);

    // ── ADMIN ────────────────────────────────────────────────────────
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/dashboard', DashboardController::class);

        Route::apiResource('guru', \App\Http\Controllers\Api\Admin\GuruController::class);
        Route::apiResource('murid', \App\Http\Controllers\Api\Admin\MuridController::class);
        // ->parameters() wajib ada: Laravel men-singularkan "kelas" jadi "kela"
        // (heuristik Inggris salah menganggap "kelas" berakhiran plural "s"),
        // sehingga {kela} di URI tidak pernah cocok dengan parameter $kelas di
        // KelasController — akibatnya route-model-binding gagal diam-diam dan
        // controller menerima instance Kelas kosong (bug lama, ditemukan saat
        // menambahkan guard destroy() — lihat show()/update()/destroy() yang
        // sebelumnya efektif tidak pernah benar-benar mengenai baris yang dituju).
        Route::apiResource('kelas', \App\Http\Controllers\Api\Admin\KelasController::class)
            ->parameters(['kelas' => 'kelas']);
        Route::apiResource('mapel', \App\Http\Controllers\Api\Admin\MapelController::class);

        // Kelola akun admin (Bagian 10) — 403 eksplisit di controller kalau bukan admin_level='super',
        // bukan cuma ->only() di sini (itu cuma soal method HTTP mana yang tersedia, bukan otorisasi).
        Route::apiResource('admin-akun', \App\Http\Controllers\Api\Admin\AdminAkunController::class)
            ->parameters(['admin-akun' => 'akun'])
            ->only(['index', 'store', 'destroy']);

        Route::get('/pendaftaran', [PendaftaranController::class, 'index']);
        Route::get('/pendaftaran/export', [PendaftaranController::class, 'export']);
        Route::get('/pendaftaran/{pendaftaran}', [PendaftaranController::class, 'show']);
        Route::post('/pendaftaran/{pendaftaran}/approve', [PendaftaranController::class, 'approve']);
        Route::post('/pendaftaran/{pendaftaran}/reject', [PendaftaranController::class, 'reject']);

        Route::apiResource('pengumuman', PengumumanController::class)->except(['show']);
        Route::post('/pengumuman/{pengumuman}/pin', [PengumumanController::class, 'togglePin']);

        Route::get('/bobot-nilai', [\App\Http\Controllers\Api\Admin\BobotNilaiController::class, 'show']);
        Route::put('/bobot-nilai', [\App\Http\Controllers\Api\Admin\BobotNilaiController::class, 'update']);

        Route::get('/kelulusan', [\App\Http\Controllers\Api\Admin\KelulusanController::class, 'index']);
        Route::post('/kelulusan/{nilaiAkhir}/verifikasi', [\App\Http\Controllers\Api\Admin\KelulusanController::class, 'verifikasi']);

        Route::get('/export/laporan/count', [\App\Http\Controllers\Api\Admin\LaporanController::class, 'count']);
        Route::get('/export/laporan/preview', [\App\Http\Controllers\Api\Admin\LaporanController::class, 'preview']);
        Route::get('/export/laporan', [\App\Http\Controllers\Api\Admin\LaporanController::class, 'export']);

        // Penugasan Mengajar — pengganti aturan lama "1 guru = 1 mapel".
        Route::get('/penugasan', [\App\Http\Controllers\Api\Admin\PenugasanController::class, 'index']);
        Route::post('/penugasan', [\App\Http\Controllers\Api\Admin\PenugasanController::class, 'store']);
        Route::post('/penugasan/{penugasan}/setujui', [\App\Http\Controllers\Api\Admin\PenugasanController::class, 'setujui']);
        Route::post('/penugasan/{penugasan}/tolak', [\App\Http\Controllers\Api\Admin\PenugasanController::class, 'tolak']);
        Route::delete('/penugasan/{penugasan}', [\App\Http\Controllers\Api\Admin\PenugasanController::class, 'destroy']);
    });

    // ── GURU ─────────────────────────────────────────────────────────
    Route::middleware('role:guru')->prefix('guru')->group(function () {
        Route::get('/kelas', [\App\Http\Controllers\Api\Guru\KelasController::class, 'index']);
        Route::post('/kelas', [\App\Http\Controllers\Api\Guru\KelasController::class, 'store']);

        // Penugasan mandiri guru ke (mapel, kelas) — skema interim self-claim
        // (penugasan_guru), dipakai mobile. JANGAN diubah/dihapus dulu — lihat
        // catatan dual-support di ResolvesPenugasan.
        Route::get('/penugasan', [\App\Http\Controllers\Api\Guru\PenugasanController::class, 'index']);
        Route::get('/penugasan/tersedia', [\App\Http\Controllers\Api\Guru\PenugasanController::class, 'tersedia']);
        Route::post('/penugasan', [\App\Http\Controllers\Api\Guru\PenugasanController::class, 'store']);
        Route::delete('/penugasan/{penugasan}', [\App\Http\Controllers\Api\Guru\PenugasanController::class, 'destroy']);

        // Penugasan Mengajar — model baru (admin approve/reject), dipakai Web.
        // Sengaja path BEDA dari /guru/penugasan di atas supaya tidak bentrok
        // dengan endpoint lama milik mobile.
        Route::get('/penugasan-saya', [\App\Http\Controllers\Api\Guru\PenugasanMengajarController::class, 'index']);
        Route::get('/kelas-mapel-tersedia', [\App\Http\Controllers\Api\Guru\PenugasanMengajarController::class, 'kelasMapelTersedia']);
        Route::post('/penugasan-saya', [\App\Http\Controllers\Api\Guru\PenugasanMengajarController::class, 'store']);

        Route::apiResource('materi', \App\Http\Controllers\Api\Guru\MateriController::class);
        Route::get('/materi/{materi}/download', [\App\Http\Controllers\Api\Guru\MateriController::class, 'download']);
        Route::get('/materi/{materi}/stream', [\App\Http\Controllers\Api\Guru\MateriController::class, 'stream']);

        // ->parameters() wajib ada: Laravel men-singularkan "tugas"/"kuis" jadi
        // "tuga"/"kui" (heuristik Inggris salah mengira berakhiran plural "s"),
        // sama seperti bug "kelas" -> "kela" yang sudah ditemukan sebelumnya —
        // tanpa ini, show()/update()/destroy() menerima instance model kosong
        // lewat route-model-binding yang gagal diam-diam.
        Route::apiResource('tugas', TugasController::class)->parameters(['tugas' => 'tugas']);
        Route::get('/tugas/{tugas}/submissions', [TugasController::class, 'submissions']);
        Route::get('/tugas/{tugas}/submissions/{submission}/download', [TugasController::class, 'downloadSubmission']);
        Route::get('/tugas/{tugas}/lampiran/download', [TugasController::class, 'downloadLampiran']);
        Route::post('/submissions/{submission}/grade', [TugasController::class, 'grade']);

        Route::apiResource('kuis', \App\Http\Controllers\Api\Guru\KuisController::class)->parameters(['kuis' => 'kuis']);
        Route::post('/kuis/{kuis}/soal', [\App\Http\Controllers\Api\Guru\SoalKuisController::class, 'store']);
        Route::get('/kuis/{kuis}/hasil', [\App\Http\Controllers\Api\Guru\KuisController::class, 'hasil']);
        Route::post('/jawaban-kuis/{jawaban}/grade', [\App\Http\Controllers\Api\Guru\KuisController::class, 'gradeEsai']);

        Route::get('/absensi', [\App\Http\Controllers\Api\Guru\AbsensiController::class, 'index']);
        Route::post('/absensi', [\App\Http\Controllers\Api\Guru\AbsensiController::class, 'store']);
        Route::get('/absensi/rekap', [\App\Http\Controllers\Api\Guru\AbsensiController::class, 'rekap']);
        Route::get('/absensi/riwayat', [\App\Http\Controllers\Api\Guru\AbsensiController::class, 'riwayat']);

        // ->parameters() wajib ada: tanpa ini Laravel pakai {pertemuan_online} (dari
        // nama resource kebab-case "pertemuan-online"), padahal controller pakai
        // $pertemuan — nama tidak cocok jadi Eloquent implicit binding gagal diam-diam
        // (model kosong ke-inject, bukan exception, jadi kelihatannya "jalan" padahal
        // salah). Ditemukan lewat DELETE yang harusnya 200/422 malah 403 karena guru_id
        // model kosong itu null. Pola sama seperti fix kelas/tugas/kuis di atas.
        Route::apiResource('pertemuan-online', PertemuanOnlineController::class)
            ->except(['show'])
            ->parameters(['pertemuan-online' => 'pertemuan']);
        Route::post('/pertemuan-online/{pertemuan}/batalkan', [PertemuanOnlineController::class, 'batalkan']);

        Route::get('/nilai-akhir', [\App\Http\Controllers\Api\Guru\NilaiAkhirController::class, 'index']);
        // Route literal ini WAJIB didaftarkan sebelum '/nilai-akhir/{muridId}/hitung' di
        // bawah — kalau tidak, "kirim-admin-massal" akan cocok ke pola {muridId} duluan.
        Route::post('/nilai-akhir/kirim-admin-massal', [\App\Http\Controllers\Api\Guru\NilaiAkhirController::class, 'kirimAdminMassal']);
        Route::post('/nilai-akhir/{muridId}/hitung', [\App\Http\Controllers\Api\Guru\NilaiAkhirController::class, 'hitung']);
        Route::put('/nilai-akhir/{nilaiAkhir}', [\App\Http\Controllers\Api\Guru\NilaiAkhirController::class, 'update']);
        Route::post('/nilai-akhir/{nilaiAkhir}/kirim-admin', [\App\Http\Controllers\Api\Guru\NilaiAkhirController::class, 'kirimAdmin']);

        // Bagian 5 — komponen nilai fleksibel per mapel (pengganti bobot tugas/kuis/absensi/ujian yang kaku).
        Route::get('/komponen-nilai', [\App\Http\Controllers\Api\Guru\KomponenNilaiController::class, 'index']);
        Route::post('/komponen-nilai', [\App\Http\Controllers\Api\Guru\KomponenNilaiController::class, 'store']);
        Route::put('/komponen-nilai/{komponenNilai}', [\App\Http\Controllers\Api\Guru\KomponenNilaiController::class, 'update']);
        Route::delete('/komponen-nilai/{komponenNilai}', [\App\Http\Controllers\Api\Guru\KomponenNilaiController::class, 'destroy']);
        Route::get('/nilai-komponen/matriks', [\App\Http\Controllers\Api\Guru\KomponenNilaiController::class, 'matriks']);
        Route::put('/nilai-komponen/bulk', [\App\Http\Controllers\Api\Guru\KomponenNilaiController::class, 'simpanBulk']);

        Route::get('/export/tugas', [ExportController::class, 'tugas']);
        Route::get('/export/nilai', [ExportController::class, 'nilai']);
        Route::get('/export/absensi', [\App\Http\Controllers\Api\Guru\ExportController::class, 'absensi']);
    });

    // ── MURID ────────────────────────────────────────────────────────
    Route::middleware('role:murid')->prefix('murid')->group(function () {
        Route::get('/mapel', [\App\Http\Controllers\Api\Murid\MapelController::class, 'index']);
        Route::get('/mapel/{mapel}', [\App\Http\Controllers\Api\Murid\MapelController::class, 'show']);

        Route::get('/materi', [\App\Http\Controllers\Api\Murid\MateriDownloadController::class, 'index']);
        Route::get('/materi/{materi}/download', [\App\Http\Controllers\Api\Murid\MateriDownloadController::class, 'download']);
        Route::get('/materi/{materi}/stream', [\App\Http\Controllers\Api\Murid\MateriDownloadController::class, 'stream']);
        Route::post('/materi/{materi}/selesai-tonton', [\App\Http\Controllers\Api\Murid\MateriDownloadController::class, 'selesaiTonton']);

        Route::get('/tugas', [TugasSubmitController::class, 'index']);
        Route::get('/tugas/{tugas}', [\App\Http\Controllers\Api\Murid\TugasSubmitController::class, 'show']);
        Route::post('/tugas/{tugas}/submit', [TugasSubmitController::class, 'submit']);
        Route::delete('/tugas/{tugas}/submission', [TugasSubmitController::class, 'batalkan']);
        Route::get('/tugas/{tugas}/submission/download', [TugasSubmitController::class, 'downloadSubmission']);
        Route::get('/tugas/{tugas}/lampiran/download', [TugasSubmitController::class, 'downloadLampiran']);

        Route::get('/kuis', [\App\Http\Controllers\Api\Murid\KuisKerjakanController::class, 'index']);
        Route::get('/kuis/{kuis}/mulai', [\App\Http\Controllers\Api\Murid\KuisKerjakanController::class, 'mulai']);
        Route::post('/kuis/{kuis}/jawab', [\App\Http\Controllers\Api\Murid\KuisKerjakanController::class, 'jawab']);
        Route::post('/kuis/{kuis}/submit', [\App\Http\Controllers\Api\Murid\KuisKerjakanController::class, 'submit']);

        Route::get('/absensi', [\App\Http\Controllers\Api\Murid\AbsensiController::class, 'index']);
        Route::post('/absensi', [\App\Http\Controllers\Api\Murid\AbsensiController::class, 'store']);
        Route::get('/leaderboard', [\App\Http\Controllers\Api\Murid\LeaderboardController::class, 'index']);
        Route::get('/nilai-akhir', [\App\Http\Controllers\Api\Murid\NilaiController::class, 'index']);
        Route::get('/rapor/{muridId}', [\App\Http\Controllers\Api\Murid\NilaiController::class, 'rapor']);
    });
});
