<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiskusiReaksi extends Model
{
    protected $table = 'diskusi_reaksi';

    protected $fillable = ['diskusi_id', 'user_id', 'emoji'];

    public function diskusi()
    {
        return $this->belongsTo(Diskusi::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
