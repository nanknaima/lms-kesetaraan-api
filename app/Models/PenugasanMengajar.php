<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Penugasan mengajar guru ke satu kombinasi (kelas, mapel) — pengganti aturan
 * lama "1 guru = 1 mapel". Dikelola admin (buat/setujui/tolak/cabut) atau
 * diajukan sendiri oleh guru untuk disetujui admin. Lihat migration
 * create_penugasan_mengajar_table untuk aturan "maksimal satu baris
 * disetujui per (kelas_id, mapel_id)" yang ditegakkan di controller.
 */
class PenugasanMengajar extends Model
{
    protected $table = 'penugasan_mengajar';

    protected $fillable = [
        'guru_id', 'kelas_id', 'mapel_id', 'status', 'diajukan_oleh', 'catatan_guru', 'catatan_admin',
    ];

    public function guru()
    {
        return $this->belongsTo(User::class, 'guru_id');
    }

    public function kelas()
    {
        return $this->belongsTo(Kelas::class);
    }

    public function mapel()
    {
        return $this->belongsTo(Mapel::class);
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('status', 'disetujui');
    }

    /**
     * Cek apakah sudah ada baris 'disetujui' lain untuk kombinasi (kelas_id,
     * mapel_id) ini — aturan inti "maksimal satu guru aktif per kelas+mapel".
     * Dipakai Admin\PenugasanController (store/setujui) & Guru\PenugasanMengajarController
     * (store) supaya aturannya konsisten di satu tempat. $kecualiId dipakai
     * saat menyetujui baris itu sendiri, supaya tidak dianggap bentrok dengan dirinya sendiri.
     */
    public static function adaKonflikAktif(int $kelasId, int $mapelId, ?int $kecualiId = null): bool
    {
        return static::aktif()
            ->where('kelas_id', $kelasId)
            ->where('mapel_id', $mapelId)
            ->when($kecualiId, fn ($q) => $q->where('id', '!=', $kecualiId))
            ->exists();
    }
}
