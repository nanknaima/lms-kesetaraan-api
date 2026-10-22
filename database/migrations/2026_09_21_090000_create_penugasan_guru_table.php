<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Penugasan guru — satu guru bisa mengampu BANYAK (mapel, kelas), diklaim
 * mandiri secara interaktif oleh guru sendiri (bukan cuma ditentukan admin).
 * Satu (mapel, kelas) hanya boleh dipegang SATU guru (unique) — klaim
 * eksklusif, first-come-first-served, supaya jelas & tidak ambigu ketika
 * banyak akun guru sama-sama ingin mengambil mata pelajaran.
 *
 * guru_profile.mapel_id (skema lama, 1 guru = 1 mapel) TETAP dipertahankan
 * apa adanya untuk backward-compat dengan Web (yang masih pakai model lama)
 * — lihat User::mapelDiampu() yang sekarang membaca dari sini sebagai
 * fallback bila guru itu belum/tidak punya banyak penugasan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penugasan_guru', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guru_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('mapel_id')->constrained('mapel')->cascadeOnDelete();
            $table->foreignId('kelas_id')->constrained('kelas')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['mapel_id', 'kelas_id']);
            $table->index('guru_id');
        });

        // Migrasi data lama: guru yang sudah ditugaskan admin ke satu mapel
        // (guru_profile.mapel_id) otomatis dapat penugasan untuk semua kelas
        // yang paket-nya cocok, supaya materi/tugas/kuis yang sudah ada tetap
        // konsisten (guru tetap "memegang" apa yang sudah diampunya).
        $guruProfiles = DB::table('guru_profile')->whereNotNull('mapel_id')->get();
        foreach ($guruProfiles as $gp) {
            $mapel = DB::table('mapel')->find($gp->mapel_id);
            if (! $mapel) {
                continue;
            }
            $kelasList = DB::table('kelas')->where('paket', $mapel->paket)->get();
            foreach ($kelasList as $kelas) {
                DB::table('penugasan_guru')->insertOrIgnore([
                    'guru_id' => $gp->user_id,
                    'mapel_id' => $gp->mapel_id,
                    'kelas_id' => $kelas->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('penugasan_guru');
    }
};
