<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Generalisasi chat_pesan dari murid<->guru saja menjadi antar-siapapun
 * (murid<->murid sekelas, guru<->guru, murid<->guru pengajar). Kolom
 * murid_id/guru_id yang berasumsi satu sisi selalu murid & sisi lain selalu
 * guru diganti pasangan generik pengguna_a_id/pengguna_b_id (disimpan
 * terurut: a = id lebih kecil, b = id lebih besar) supaya satu thread punya
 * satu baris kunci pasangan yang konsisten siapa pun yang memulai chat.
 * Tabel masih kosong (belum ada pesan produksi), jadi aman diubah langsung.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_pesan', function (Blueprint $table) {
            $table->dropForeign(['murid_id']);
            $table->dropForeign(['guru_id']);
            $table->dropColumn(['murid_id', 'guru_id']);
        });

        Schema::table('chat_pesan', function (Blueprint $table) {
            $table->foreignId('pengguna_a_id')->after('id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('pengguna_b_id')->after('pengguna_a_id')->constrained('users')->cascadeOnDelete();
            $table->index(['pengguna_a_id', 'pengguna_b_id']);
        });
    }

    public function down(): void
    {
        Schema::table('chat_pesan', function (Blueprint $table) {
            $table->dropForeign(['pengguna_a_id']);
            $table->dropForeign(['pengguna_b_id']);
            $table->dropColumn(['pengguna_a_id', 'pengguna_b_id']);
        });

        Schema::table('chat_pesan', function (Blueprint $table) {
            $table->foreignId('murid_id')->after('id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('guru_id')->after('murid_id')->constrained('users')->cascadeOnDelete();
            $table->index(['murid_id', 'guru_id']);
        });
    }
};
