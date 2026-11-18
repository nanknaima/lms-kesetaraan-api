<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HonorGuru extends Model
{
    protected $table = 'honor_guru';

    protected $fillable = [
        'guru_id', 'periode', 'keterangan', 'jumlah', 'mata_uang', 'status',
        'tanggal_bayar', 'metode_bayar', 'dibuat_oleh_id', 'dibayar_oleh_id',
    ];

    protected $casts = ['jumlah' => 'decimal:2', 'tanggal_bayar' => 'date'];

    public function guru()
    {
        return $this->belongsTo(User::class, 'guru_id');
    }
}
