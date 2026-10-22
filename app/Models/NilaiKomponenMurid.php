<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NilaiKomponenMurid extends Model
{
    protected $table = 'nilai_komponen_murid';

    protected $fillable = ['komponen_nilai_id', 'murid_id', 'nilai', 'catatan'];

    protected $casts = ['nilai' => 'float'];

    public function komponen()
    {
        return $this->belongsTo(KomponenNilai::class, 'komponen_nilai_id');
    }

    public function murid()
    {
        return $this->belongsTo(User::class, 'murid_id');
    }
}
