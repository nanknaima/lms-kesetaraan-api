<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jawaban_kuis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kuis_id')->constrained('kuis')->cascadeOnDelete();
            $table->foreignId('murid_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('soal_id')->constrained('soal_kuis')->cascadeOnDelete();
            $table->text('jawaban')->nullable();
            $table->decimal('skor', 5, 2)->nullable();
            $table->boolean('auto_graded')->default(false);
            $table->timestamps();

            $table->unique(['kuis_id', 'murid_id', 'soal_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jawaban_kuis');
    }
};
