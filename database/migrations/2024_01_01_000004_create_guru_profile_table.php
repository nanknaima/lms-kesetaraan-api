<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guru_profile', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()
                ->constrained('users')->cascadeOnDelete();
            // Aturan bisnis #1: 1 guru HANYA memegang 1 mapel.
            // Divalidasi juga di GuruObserver/FormRequest, bukan hanya di DB.
            $table->foreignId('mapel_id')->constrained('mapel')->restrictOnDelete();
            $table->string('no_hp')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guru_profile');
    }
};
