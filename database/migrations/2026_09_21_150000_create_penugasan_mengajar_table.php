<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Penugasan Mengajar — pengganti aturan lama "1 guru = 1 mapel (otomatis
 * akses semua kelas di paket itu)". Sekarang guru bisa mengampu BANYAK
 * kombinasi (kelas, mapel) spesifik, dikelola admin (buat/setujui/tolak/
 * cabut) ATAU diajukan sendiri oleh guru untuk disetujui admin.
 *
 * Baris di sini TIDAK PERNAH di-hard-delete — status 'ditolak'/'dicabut'
 * tetap disimpan sebagai histori supaya nilai/tugas yang sudah dibuat guru
 * itu tetap bisa ditelusuri terhubung ke penugasan mana. Aturan "maksimal
 * satu baris status=disetujui per (kelas_id, mapel_id)" ditegakkan di level
 * aplikasi (controller), bukan unique constraint DB, karena baris
 * ditolak/dicabut boleh menumpuk untuk kombinasi yang sama.
 *
 * guru_profile.mapel_id (skema lama) & tabel penugasan_guru (skema interim
 * self-claim yang dipakai mobile) TETAP dipertahankan apa adanya untuk saat
 * ini — lihat catatan dual-support di ResolvesPenugasan/controller Guru.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penugasan_mengajar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guru_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('kelas_id')->constrained('kelas')->cascadeOnDelete();
            $table->foreignId('mapel_id')->constrained('mapel')->cascadeOnDelete();
            $table->enum('status', ['diajukan', 'disetujui', 'ditolak', 'dicabut'])
                ->default('disetujui');
            $table->enum('diajukan_oleh', ['admin', 'guru'])->default('admin');
            $table->text('catatan_guru')->nullable();
            $table->text('catatan_admin')->nullable();
            $table->timestamps();

            $table->index(['guru_id', 'status']);
            $table->index(['kelas_id', 'mapel_id', 'status']);
        });

        // Migrasi data lama: guru (termasuk yang sudah nonaktif/soft-deleted,
        // supaya histori tetap konsisten) yang guru_profile-nya sudah diisi
        // mapel_id otomatis dapat penugasan disetujui untuk semua kelas yang
        // paket-nya cocok — akses guru PERSIS SAMA seperti sebelum perubahan.
        $guruProfiles = DB::table('guru_profile')->whereNotNull('mapel_id')->get();
        $now = now();
        foreach ($guruProfiles as $gp) {
            $mapel = DB::table('mapel')->find($gp->mapel_id);
            if (! $mapel) {
                continue;
            }
            $kelasList = DB::table('kelas')->where('paket', $mapel->paket)->get();
            $rows = [];
            foreach ($kelasList as $kelas) {
                $rows[] = [
                    'guru_id' => $gp->user_id,
                    'kelas_id' => $kelas->id,
                    'mapel_id' => $gp->mapel_id,
                    'status' => 'disetujui',
                    'diajukan_oleh' => 'admin',
                    'catatan_guru' => null,
                    'catatan_admin' => 'Migrasi otomatis dari sistem lama (1 guru = 1 mapel)',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            if ($rows !== []) {
                DB::table('penugasan_mengajar')->insert($rows);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('penugasan_mengajar');
    }
};
