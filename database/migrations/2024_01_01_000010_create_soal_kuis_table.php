<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('soal_kuis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kuis_id')->constrained('kuis')->cascadeOnDelete();
            $table->text('pertanyaan');
            $table->enum('tipe', ['pilihan_ganda', 'benar_salah', 'isian_singkat', 'esai']);
            $table->json('opsi')->nullable(); // untuk pilihan_ganda: [{key, text}]
            $table->string('jawaban_benar')->nullable(); // null untuk esai
            $table->unsignedInteger('poin')->default(10);
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('soal_kuis');
    }
};
