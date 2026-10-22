<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE materi MODIFY tipe ENUM('pdf', 'video', 'link', 'teks', 'ppt', 'docx', 'gambar') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE materi MODIFY tipe ENUM('pdf', 'video', 'link', 'teks') NOT NULL");
    }
};
