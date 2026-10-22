<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kode OTP 6 digit untuk fitur lupa password (dikirim ke email).
 * Terpisah dari tabel bawaan Laravel `password_reset_tokens` (itu untuk
 * alur link-email default; di sini kita pakai kode OTP pendek).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_reset_otps', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('otp'); // di-hash, tidak disimpan plain
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_otps');
    }
};
