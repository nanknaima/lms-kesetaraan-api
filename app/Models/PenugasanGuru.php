<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris = satu guru mengampu satu mata pelajaran untuk satu kelas.
 * Diklaim mandiri oleh guru lewat Api\Guru\PenugasanController — lihat
 * migration create_penugasan_guru_table untuk aturan uniknya.
 */
class PenugasanGuru extends Model
{
    protected $table = 'penugasan_guru';
    protected $fillable = ['guru_id', 'mapel_id', 'kelas_id'];

    public function guru()
    {
        return $this->belongsTo(User::class, 'guru_id');
    }

    public function mapel()
    {
        return $this->belongsTo(Mapel::class);
    }

    public function kelas()
    {
        return $this->belongsTo(Kelas::class);
    }
}
