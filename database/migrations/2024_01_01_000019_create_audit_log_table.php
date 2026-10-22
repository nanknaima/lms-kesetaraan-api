<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('aksi'); // create|update|delete|approve|reject|...
            $table->string('model');
            $table->unsignedBigInteger('model_id')->nullable();
            $table->json('data_before')->nullable();
            $table->json('data_after')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['model', 'model_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log');
    }
};
