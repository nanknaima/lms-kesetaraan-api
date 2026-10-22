<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pendaftaran', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('email');
            $table->string('no_hp');
            $table->enum('paket', ['A', 'B', 'C']);
            $table->date('tgl_lahir');
            $table->text('alamat')->nullable();
            $table->string('dokumen_url')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->string('nis_generated')->nullable();
            $table->foreignId('diverifikasi_oleh')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->text('alasan_reject')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pendaftaran');
    }
};
