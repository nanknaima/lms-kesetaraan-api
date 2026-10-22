<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tugas extends Model
{
    protected $table = 'tugas';
    protected $fillable = ['mapel_id', 'guru_id', 'kelas_id', 'judul', 'instruksi', 'lampiran_url', 'deadline'];
    protected $casts = ['deadline' => 'datetime'];

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

    public function submissions()
    {
        return $this->hasMany(SubmissionTugas::class);
    }

    public function isLewatDeadline(): bool
    {
        return now()->greaterThan($this->deadline);
    }
}
