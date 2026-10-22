<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NilaiAkhir extends Model
{
    protected $table = 'nilai_akhir';
    protected $fillable = [
        'murid_id', 'mapel_id', 'nilai_tugas', 'nilai_kuis', 'nilai_absensi', 'nilai_ujian',
        'rincian_komponen', 'nilai_akhir', 'status_kelulusan', 'catatan_guru', 'rekomendasi_guru_lulus',
        'dikirim_ke_admin_at', 'verified_by_admin', 'verified_by', 'dirilis_ke_murid',
    ];

    protected $casts = ['rincian_komponen' => 'array', 'dikirim_ke_admin_at' => 'datetime'];

    public function murid()
    {
        return $this->belongsTo(User::class, 'murid_id');
    }

    public function mapel()
    {
        return $this->belongsTo(Mapel::class);
    }
}
