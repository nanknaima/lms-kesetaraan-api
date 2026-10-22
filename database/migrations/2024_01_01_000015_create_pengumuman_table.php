<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengumuman', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
            $table->string('judul');
            $table->text('konten');
            $table->string('gambar_url')->nullable(); // max 2MB, divalidasi di FormRequest
            $table->enum('target', ['semua', 'paket', 'kelas'])->default('semua');
            $table->unsignedBigInteger('target_id')->nullable(); // id paket(enum→string)/kelas
            $table->date('tanggal_mulai');
            $table->date('tanggal_berakhir');
            $table->boolean('pinned')->default(false);
            $table->enum('status_publish', ['draft', 'published', 'expired'])->default('draft');
            $table->timestamps();

            $table->index(['status_publish', 'tanggal_mulai', 'tanggal_berakhir']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengumuman');
    }
};
