<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_pesan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('murid_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('guru_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('pengirim_id')->constrained('users')->cascadeOnDelete();
            $table->text('pesan');
            $table->timestamp('dibaca_at')->nullable();
            $table->timestamps();

            $table->index(['murid_id', 'guru_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_pesan');
    }
};
