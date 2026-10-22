<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nilai_akhir', function (Blueprint $table) {
            $table->id();
            $table->foreignId('murid_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('mapel_id')->constrained('mapel')->cascadeOnDelete();
            $table->decimal('nilai_tugas', 5, 2)->nullable();
            $table->decimal('nilai_kuis', 5, 2)->nullable();
            $table->decimal('nilai_absensi', 5, 2)->nullable();
            $table->decimal('nilai_ujian', 5, 2)->nullable();
            $table->decimal('nilai_akhir', 5, 2)->nullable(); // hasil kalkulasi bobot
            $table->enum('status_kelulusan', ['lulus', 'tidak_lulus', 'pending'])->default('pending');
            $table->text('catatan_guru')->nullable();
            $table->boolean('rekomendasi_guru_lulus')->nullable();
            $table->boolean('verified_by_admin')->default(false);
            $table->foreignId('verified_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->boolean('dirilis_ke_murid')->default(false);
            $table->timestamps();

            $table->unique(['murid_id', 'mapel_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nilai_akhir');
    }
};
