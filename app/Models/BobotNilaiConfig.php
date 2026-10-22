<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BobotNilaiConfig extends Model
{
    protected $table = 'bobot_nilai_config';
    protected $fillable = ['mapel_id', 'bobot_tugas', 'bobot_kuis', 'bobot_absensi', 'bobot_ujian', 'passing_grade'];
}
