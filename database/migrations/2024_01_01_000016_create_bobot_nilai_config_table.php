<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bobot_nilai_config', function (Blueprint $table) {
            $table->id();
            // mapel_id null = konfigurasi default global
            $table->foreignId('mapel_id')->nullable()->unique()
                ->constrained('mapel')->cascadeOnDelete();
            $table->decimal('bobot_tugas', 5, 2)->default(30);
            $table->decimal('bobot_kuis', 5, 2)->default(30);
            $table->decimal('bobot_absensi', 5, 2)->default(10);
            $table->decimal('bobot_ujian', 5, 2)->default(30);
            $table->decimal('passing_grade', 5, 2)->default(75);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bobot_nilai_config');
    }
};
