<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diskusi_reaksi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diskusi_id')->constrained('diskusi')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('emoji');
            $table->timestamps();

            $table->unique(['diskusi_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diskusi_reaksi');
    }
};
