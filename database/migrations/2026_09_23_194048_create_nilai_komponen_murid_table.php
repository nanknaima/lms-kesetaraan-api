<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nilai_komponen_murid', function (Blueprint $table) {
            $table->id();
            $table->foreignId('komponen_nilai_id')->constrained('komponen_nilai')->cascadeOnDelete();
            $table->foreignId('murid_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('nilai', 5, 2)->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(['komponen_nilai_id', 'murid_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nilai_komponen_murid');
    }
};
