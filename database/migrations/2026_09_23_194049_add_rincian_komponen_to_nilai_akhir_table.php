<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `rincian_komponen` = snapshot rincian nilai per komponen saat nilai
     * akhir dihitung (nama, bobot_persen, nilai) — dipakai UI & export supaya
     * tetap bisa menampilkan rincian meski komponen_nilai-nya kelak diubah/
     * dihapus guru. `nilai_kuis` SENGAJA dibiarkan ada di tabel (data lama
     * yang sudah dirilis tidak diubah) tapi tidak lagi diisi untuk baris baru
     * — nilai kuis tidak lagi berkontribusi ke nilai akademik (lihat Bagian 5).
     */
    public function up(): void
    {
        Schema::table('nilai_akhir', function (Blueprint $table) {
            $table->json('rincian_komponen')->nullable()->after('nilai_ujian');
        });
    }

    public function down(): void
    {
        Schema::table('nilai_akhir', function (Blueprint $table) {
            $table->dropColumn('rincian_komponen');
        });
    }
};
