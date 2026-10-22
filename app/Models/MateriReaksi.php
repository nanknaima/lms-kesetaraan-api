<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MateriReaksi extends Model
{
    protected $table = 'materi_reaksi';

    protected $fillable = ['materi_id', 'user_id', 'emoji'];

    public function materi()
    {
        return $this->belongsTo(Materi::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Kelompokkan reaksi per emoji: [['emoji' => '👍', 'count' => 3, 'reacted' => true], ...] */
    public static function summarize($reaksi, int $userId): array
    {
        return $reaksi
            ->groupBy('emoji')
            ->map(fn ($group, $emoji) => [
                'emoji' => $emoji,
                'count' => $group->count(),
                'reacted' => $group->contains('user_id', $userId),
            ])
            ->values()
            ->all();
    }
}
