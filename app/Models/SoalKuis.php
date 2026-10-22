<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SoalKuis extends Model
{
    protected $table = 'soal_kuis';
    protected $fillable = ['kuis_id', 'pertanyaan', 'tipe', 'opsi', 'jawaban_benar', 'poin', 'urutan'];
    protected $casts = ['opsi' => 'array'];
}
