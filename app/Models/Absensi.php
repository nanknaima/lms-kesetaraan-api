<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Absensi extends Model
{
    protected $table = 'absensi';
    protected $fillable = ['mapel_id', 'murid_id', 'tanggal', 'status', 'keterangan', 'otomatis'];
    protected $casts = ['tanggal' => 'date', 'otomatis' => 'boolean'];

    public function murid()
    {
        return $this->belongsTo(User::class, 'murid_id');
    }

    public function mapel()
    {
        return $this->belongsTo(Mapel::class);
    }
}
