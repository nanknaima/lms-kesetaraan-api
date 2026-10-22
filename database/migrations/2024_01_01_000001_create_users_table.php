<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('email')->unique();
            $table->string('password');
            $table->enum('role', ['admin', 'guru', 'murid']);
            $table->string('nis')->nullable()->unique(); // khusus murid
            $table->string('nip')->nullable()->unique(); // khusus guru
            $table->enum('status', ['aktif', 'nonaktif', 'lulus', 'bermasalah'])->default('aktif');
            $table->string('avatar_url')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes(); // aturan bisnis #6: akun dihapus = soft delete

            $table->index('role');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
