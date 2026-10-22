<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KomponenNilai extends Model
{
    protected $table = 'komponen_nilai';

    protected $fillable = ['mapel_id', 'nama', 'tipe', 'bobot_persen', 'urutan'];

    protected $casts = ['bobot_persen' => 'float'];

    public function mapel()
    {
        return $this->belongsTo(Mapel::class);
    }

    public function nilaiMurid()
    {
        return $this->hasMany(NilaiKomponenMurid::class);
    }
}
