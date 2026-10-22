<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Diskusi extends Model
{
    protected $table = 'diskusi';

    protected $fillable = ['mapel_id', 'materi_id', 'user_id', 'pesan'];

    public function mapel()
    {
        return $this->belongsTo(Mapel::class, 'mapel_id');
    }

    public function materi()
    {
        return $this->belongsTo(Materi::class, 'materi_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reaksi()
    {
        return $this->hasMany(DiskusiReaksi::class);
    }
}
