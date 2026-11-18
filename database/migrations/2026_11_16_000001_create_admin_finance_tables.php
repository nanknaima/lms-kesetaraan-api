<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagihan_murid', function (Blueprint $table) {
            $table->id();
            $table->foreignId('murid_id')->constrained('users')->restrictOnDelete();
            $table->string('periode', 7);
            $table->string('keterangan')->nullable();
            $table->decimal('jumlah', 15, 2);
            $table->enum('mata_uang', ['IDR', 'NTD']);
            $table->date('jatuh_tempo')->nullable();
            $table->foreignId('dibuat_oleh_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['murid_id', 'periode']);
            $table->index(['periode', 'mata_uang']);
        });

        Schema::create('pembayaran_murid', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tagihan_id')->constrained('tagihan_murid')->restrictOnDelete();
            $table->decimal('jumlah', 15, 2);
            $table->date('tanggal');
            $table->enum('metode', ['tunai', 'transfer', 'lainnya']);
            $table->text('catatan')->nullable();
            $table->foreignId('dicatat_oleh_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['tanggal', 'metode']);
        });

        Schema::create('honor_guru', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guru_id')->constrained('users')->restrictOnDelete();
            $table->string('periode', 7);
            $table->string('keterangan')->nullable();
            $table->decimal('jumlah', 15, 2);
            $table->enum('mata_uang', ['IDR', 'NTD']);
            $table->enum('status', ['belum_dibayar', 'dibayar'])->default('belum_dibayar');
            $table->date('tanggal_bayar')->nullable();
            $table->enum('metode_bayar', ['tunai', 'transfer', 'lainnya'])->nullable();
            $table->foreignId('dibuat_oleh_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('dibayar_oleh_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['guru_id', 'periode']);
            $table->index(['periode', 'status', 'mata_uang']);
        });

        Schema::create('transaksi_keuangan', function (Blueprint $table) {
            $table->id();
            $table->enum('jenis', ['pemasukan', 'pengeluaran']);
            $table->enum('sumber', ['pembayaran', 'honor', 'manual']);
            $table->string('keterangan');
            $table->decimal('jumlah', 15, 2);
            $table->enum('mata_uang', ['IDR', 'NTD']);
            $table->date('tanggal');
            $table->foreignId('pembayaran_murid_id')->nullable()
                ->unique()->constrained('pembayaran_murid')->restrictOnDelete();
            $table->foreignId('honor_guru_id')->nullable()
                ->unique()->constrained('honor_guru')->restrictOnDelete();
            $table->foreignId('dicatat_oleh_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['tanggal', 'mata_uang', 'jenis']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaksi_keuangan');
        Schema::dropIfExists('honor_guru');
        Schema::dropIfExists('pembayaran_murid');
        Schema::dropIfExists('tagihan_murid');
    }
};
