<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `status_kelulusan` defaultnya SUDAH 'pending' sejak baris dibuat (lihat
     * migration nilai_akhir), dan kirimAdmin() lama juga cuma men-set ulang
     * 'pending' — jadi status_kelulusan TIDAK BISA dipakai untuk membedakan
     * "belum pernah dikirim ke admin" vs "sudah dikirim, menunggu admin",
     * keduanya sama-sama terlihat 'pending'. Kolom timestamp baru ini kasih
     * sinyal yang jelas & idempoten: null = belum pernah dikirim.
     */
    public function up(): void
    {
        Schema::table('nilai_akhir', function (Blueprint $table) {
            $table->timestamp('dikirim_ke_admin_at')->nullable()->after('rekomendasi_guru_lulus');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nilai_akhir', function (Blueprint $table) {
            $table->dropColumn('dikirim_ke_admin_at');
        });
    }
};
