<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tugas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mapel_id')->constrained('mapel')->cascadeOnDelete();
            $table->foreignId('guru_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('kelas_id')->constrained('kelas')->cascadeOnDelete();
            $table->string('judul');
            $table->text('instruksi')->nullable();
            $table->string('lampiran_url')->nullable();
            $table->dateTime('deadline');
            $table->timestamps();

            $table->index(['mapel_id', 'kelas_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tugas');
    }
};
