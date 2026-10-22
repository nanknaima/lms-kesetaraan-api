<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubmissionTugas extends Model
{
    protected $table = 'submission_tugas';
    protected $fillable = ['tugas_id', 'murid_id', 'file_url', 'jawaban', 'nilai', 'feedback', 'submitted_at', 'status'];
    protected $casts = ['submitted_at' => 'datetime'];

    public function tugas()
    {
        return $this->belongsTo(Tugas::class);
    }

    public function murid()
    {
        return $this->belongsTo(User::class, 'murid_id');
    }
}
