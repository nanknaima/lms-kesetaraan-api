<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submission_tugas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tugas_id')->constrained('tugas')->cascadeOnDelete();
            $table->foreignId('murid_id')->constrained('users')->cascadeOnDelete();
            $table->string('file_url')->nullable();
            $table->text('jawaban')->nullable();
            $table->decimal('nilai', 5, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->enum('status', ['belum', 'terlambat', 'dinilai'])->default('belum');
            $table->timestamps();

            $table->unique(['tugas_id', 'murid_id']); // 1 murid 1 submission per tugas
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_tugas');
    }
};
