<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TagihanMurid extends Model
{
    protected $table = 'tagihan_murid';

    protected $fillable = [
        'murid_id', 'periode', 'keterangan', 'jumlah', 'mata_uang', 'jatuh_tempo', 'dibuat_oleh_id',
    ];

    protected $casts = ['jumlah' => 'decimal:2', 'jatuh_tempo' => 'date'];

    public function murid()
    {
        return $this->belongsTo(User::class, 'murid_id');
    }

    public function pembayaran()
    {
        return $this->hasMany(PembayaranMurid::class, 'tagihan_id');
    }
}
