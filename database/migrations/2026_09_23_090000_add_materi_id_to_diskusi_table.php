<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Diskusi per MATERI (bukan cuma per mapel) — panel komentar yang menempel
 * langsung di bawah tiap item materi. `mapel_id` TETAP ada & tidak diubah
 * untuk kompatibilitas mundur (diskusi lama level-mapel, materi_id NULL).
 * cascadeOnDelete: materi dihapus -> diskusinya ikut terhapus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diskusi', function (Blueprint $table) {
            $table->foreignId('materi_id')->nullable()->after('mapel_id')
                ->constrained('materi')->cascadeOnDelete();
            $table->index('materi_id');
        });
    }

    public function down(): void
    {
        Schema::table('diskusi', function (Blueprint $table) {
            $table->dropForeign(['materi_id']);
            $table->dropColumn('materi_id');
        });
    }
};
