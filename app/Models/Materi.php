<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Materi extends Model
{
    protected $table = 'materi';
    protected $fillable = ['mapel_id', 'guru_id', 'kelas_id', 'judul', 'tipe', 'file_url', 'deskripsi'];

    public function mapel()
    {
        return $this->belongsTo(Mapel::class);
    }

    public function guru()
    {
        return $this->belongsTo(User::class, 'guru_id');
    }

    public function kelas()
    {
        return $this->belongsTo(Kelas::class);
    }

    /** Komentar/pertanyaan yang menempel di materi ini — lihat Diskusi::materi_id. */
    public function diskusi()
    {
        return $this->hasMany(Diskusi::class);
    }

    /** Reaksi emoji pada POST materi itu sendiri (beda dari reaksi per komentar di DiskusiReaksi). */
    public function reaksi()
    {
        return $this->hasMany(MateriReaksi::class);
    }
}
