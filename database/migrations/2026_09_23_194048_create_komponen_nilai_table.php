<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('komponen_nilai', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mapel_id')->constrained('mapel')->cascadeOnDelete();
            $table->string('nama');
            $table->enum('tipe', ['manual', 'auto_tugas', 'auto_absensi']);
            $table->decimal('bobot_persen', 5, 2);
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('komponen_nilai');
    }
};
