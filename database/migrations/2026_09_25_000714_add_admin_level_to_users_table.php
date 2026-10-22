<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dua tingkat akun admin (Bagian 10): 'super' bisa kelola akun admin lain
     * (buat/hapus), 'staff' cuma operasional harian biasa. Default 'staff'
     * supaya akun admin BARU tidak otomatis dapat hak penuh — hanya akun
     * admin PALING AWAL (ID terkecil, sudah ada sebelum migration ini) yang
     * dipromosikan ke 'super' di sini, sekali jalan saat migrate.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('admin_level', ['super', 'staff'])->default('staff')->after('role');
        });

        $idAdminPertama = DB::table('users')->where('role', 'admin')->orderBy('id')->value('id');
        if ($idAdminPertama) {
            DB::table('users')->where('id', $idAdminPertama)->update(['admin_level' => 'super']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('admin_level');
        });
    }
};
