<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mapel_id')->constrained('mapel')->cascadeOnDelete();
            $table->foreignId('guru_id')->constrained('users')->cascadeOnDelete();
            $table->string('judul');
            $table->enum('tipe', ['pdf', 'video', 'link', 'teks']);
            $table->string('file_url')->nullable();
            $table->text('deskripsi')->nullable();
            $table->timestamps();

            $table->index('mapel_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materi');
    }
};
