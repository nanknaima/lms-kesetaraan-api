<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kelas', function (Blueprint $table) {
            $table->id();
            $table->string('nama_kelas');
            $table->enum('paket', ['A', 'B', 'C']);
            $table->string('tingkat'); // mis: I, II, III (Paket A) / VII-IX (B) / X-XII (C)
            $table->foreignId('wali_kelas_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['paket', 'tingkat']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kelas');
    }
};
