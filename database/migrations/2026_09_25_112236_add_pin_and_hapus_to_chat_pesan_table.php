<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambahan untuk fitur pin pesan & hapus pesan (untuk saya / untuk semua orang).
 * dihapus_untuk_a_at/dihapus_untuk_b_at mengikuti pola pasangan generik
 * pengguna_a_id/pengguna_b_id yang sudah ada — menandai sisi mana yang
 * menyembunyikan pesan itu dari tampilannya sendiri saja ("hapus untuk saya").
 * dihapus_untuk_semua_at menandai pesan dihapus utuh untuk kedua pihak.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_pesan', function (Blueprint $table) {
            $table->boolean('pinned')->default(false)->after('pesan');
            $table->timestamp('dihapus_untuk_a_at')->nullable()->after('dibaca_at');
            $table->timestamp('dihapus_untuk_b_at')->nullable()->after('dihapus_untuk_a_at');
            $table->timestamp('dihapus_untuk_semua_at')->nullable()->after('dihapus_untuk_b_at');
        });
    }

    public function down(): void
    {
        Schema::table('chat_pesan', function (Blueprint $table) {
            $table->dropColumn(['pinned', 'dihapus_untuk_a_at', 'dihapus_untuk_b_at', 'dihapus_untuk_semua_at']);
        });
    }
};
