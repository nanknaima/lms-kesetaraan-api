<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MuridProfile extends Model
{
    protected $table = 'murid_profile';
    protected $primaryKey = 'user_id';
    public $incrementing = false;
    protected $fillable = ['user_id', 'paket', 'kelas_id', 'tgl_lahir', 'alamat', 'status_kelulusan'];
    protected $casts = ['tgl_lahir' => 'date'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function kelas()
    {
        return $this->belongsTo(Kelas::class);
    }
}
