<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pengumuman extends Model
{
    protected $table = 'pengumuman';
    protected $fillable = [
        'admin_id', 'judul', 'konten', 'gambar_url', 'target', 'target_id',
        'tanggal_mulai', 'tanggal_berakhir', 'pinned', 'status_publish',
    ];
    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_berakhir' => 'date',
        'pinned' => 'boolean',
    ];

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function isBaru(): bool
    {
        return $this->created_at->greaterThan(now()->subDays(3));
    }

    /** Scope: hanya pengumuman yang sedang tayang (untuk slide show). */
    public function scopeTayang($query)
    {
        $today = now()->toDateString();

        return $query->where('status_publish', 'published')
            ->where('tanggal_mulai', '<=', $today)
            ->where('tanggal_berakhir', '>=', $today)
            ->orderByDesc('pinned')
            ->orderByDesc('created_at');
    }
}
