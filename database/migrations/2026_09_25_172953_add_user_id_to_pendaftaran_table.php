<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menandai pendaftaran mana yang sudah "ditindaklanjuti" jadi akun murid
 * sungguhan lewat menu Tambah Murid (Bagian G) — approve() TIDAK LAGI
 * membuat akun otomatis (NIS/password sekarang input manual admin, lihat
 * Bagian J), jadi perlu kolom terpisah untuk tahu status "sudah dibuat
 * akunnya" vs baru "disetujui tapi akun belum dibuat".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pendaftaran', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('nis_generated')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pendaftaran', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
