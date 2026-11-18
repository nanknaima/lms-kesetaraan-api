<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PembayaranMurid extends Model
{
    protected $table = 'pembayaran_murid';

    protected $fillable = [
        'tagihan_id', 'jumlah', 'tanggal', 'metode', 'catatan', 'dicatat_oleh_id',
    ];

    protected $casts = ['jumlah' => 'decimal:2', 'tanggal' => 'date'];

    public function tagihan()
    {
        return $this->belongsTo(TagihanMurid::class, 'tagihan_id');
    }
}
