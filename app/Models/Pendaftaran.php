<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pendaftaran extends Model
{
    protected $table = 'pendaftaran';
    protected $fillable = [
        'nama', 'email', 'no_hp', 'paket', 'tgl_lahir', 'alamat', 'dokumen_url',
        'status', 'nis_generated', 'user_id', 'diverifikasi_oleh', 'alasan_reject',
    ];
    protected $casts = ['tgl_lahir' => 'date'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
