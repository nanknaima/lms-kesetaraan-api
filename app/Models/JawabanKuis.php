<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JawabanKuis extends Model
{
    protected $table = 'jawaban_kuis';
    protected $fillable = ['kuis_id', 'murid_id', 'soal_id', 'jawaban', 'skor', 'auto_graded'];
    protected $casts = ['auto_graded' => 'boolean'];

    public function kuis()
    {
        return $this->belongsTo(Kuis::class);
    }

    public function soal()
    {
        return $this->belongsTo(SoalKuis::class, 'soal_id');
    }

    public function murid()
    {
        return $this->belongsTo(User::class, 'murid_id');
    }
}
