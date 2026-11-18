<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransaksiKeuangan extends Model
{
    protected $table = 'transaksi_keuangan';

    protected $fillable = [
        'jenis', 'sumber', 'keterangan', 'jumlah', 'mata_uang', 'tanggal',
        'pembayaran_murid_id', 'honor_guru_id', 'dicatat_oleh_id',
    ];

    protected $casts = ['jumlah' => 'decimal:2', 'tanggal' => 'date'];

    public function pembayaranMurid()
    {
        return $this->belongsTo(PembayaranMurid::class, 'pembayaran_murid_id');
    }

    public function honorGuru()
    {
        return $this->belongsTo(HonorGuru::class, 'honor_guru_id');
    }
}
