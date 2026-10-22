<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('murid_profile', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()
                ->constrained('users')->cascadeOnDelete();
            $table->enum('paket', ['A', 'B', 'C']);
            $table->foreignId('kelas_id')->nullable()
                ->constrained('kelas')->nullOnDelete();
            $table->date('tgl_lahir')->nullable();
            $table->text('alamat')->nullable();
            $table->enum('status_kelulusan', ['pending', 'lulus', 'tidak_lulus'])->default('pending');
            $table->timestamps();

            $table->index(['paket', 'kelas_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('murid_profile');
    }
};
