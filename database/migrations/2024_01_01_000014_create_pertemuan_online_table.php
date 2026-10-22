<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pertemuan_online', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mapel_id')->constrained('mapel')->cascadeOnDelete();
            $table->foreignId('kelas_id')->constrained('kelas')->cascadeOnDelete();
            $table->foreignId('guru_id')->constrained('users')->cascadeOnDelete();
            $table->string('judul');
            $table->string('link_meeting');
            $table->enum('platform', ['zoom', 'google_meet', 'lainnya']);
            $table->date('tanggal');
            $table->time('jam_mulai');
            $table->time('jam_selesai');
            $table->text('agenda')->nullable();
            $table->enum('status', ['terjadwal', 'terlaksana', 'batal'])->default('terjadwal');
            $table->boolean('reminder_h1_sent')->default(false);
            $table->boolean('reminder_15m_sent')->default(false);
            $table->timestamps();

            $table->index(['tanggal', 'jam_mulai']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pertemuan_online');
    }
};
