<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Materi sekarang bisa ditargetkan ke kelas tertentu (nullable — baris lama
 * tanpa kelas_id tetap dianggap "berlaku untuk seluruh paket", perilaku lama,
 * agar tidak menghilangkan akses ke materi yang sudah ada). Materi BARU yang
 * dibuat lewat form guru selalu mengisi kelas_id (lihat MateriController).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materi', function (Blueprint $table) {
            $table->foreignId('kelas_id')->nullable()->after('mapel_id')->constrained('kelas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('materi', function (Blueprint $table) {
            $table->dropForeign(['kelas_id']);
            $table->dropColumn('kelas_id');
        });
    }
};
