<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mapel extends Model
{

    protected $table = 'mapel';

    protected $fillable = ['nama_mapel', 'kode', 'paket'];


    /** @deprecated Skema lama (1 mapel = 1 guru) — dipertahankan untuk Web. Pakai [penugasan]/[guruUntukKelas] untuk data yang benar di model penugasan baru. */
    public function guru()
    {
        return $this->hasOne(GuruProfile::class);
    }

    public function materi()
    {
        return $this->hasMany(Materi::class);
    }

    public function tugas()
    {
        return $this->hasMany(Tugas::class);
    }

    /** Semua penugasan (guru+kelas) untuk mapel ini — lihat PenugasanGuru. */
    public function penugasan()
    {
        return $this->hasMany(PenugasanGuru::class);
    }

    /** Guru yang mengampu mapel ini KHUSUS untuk satu kelas tertentu (benar walau satu mapel punya banyak guru di kelas berbeda). */
    public function guruUntukKelas(int $kelasId): ?User
    {
        return $this->penugasan()->where('kelas_id', $kelasId)->with('guru')->first()?->guru;
    }

    /** Komponen penilaian fleksibel mapel ini (lihat Bagian 5 — pengganti bobot tugas/kuis/absensi/ujian yang kaku). */
    public function komponenNilai()
    {
        return $this->hasMany(KomponenNilai::class)->orderBy('urutan');
    }
}
