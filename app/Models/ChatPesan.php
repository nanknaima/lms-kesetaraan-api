<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Pesan personal (DM) antara dua pengguna mana pun (murid<->murid sekelas,
 * guru<->guru, murid<->guru pengajar) — beda dari [Diskusi] yang forum
 * publik per-mapel. pengguna_a_id/pengguna_b_id selalu disimpan terurut
 * (a = id lebih kecil) supaya satu percakapan punya satu kunci pasangan yang
 * konsisten; pengirim_id menandai siapa penulis pesan itu sendiri.
 */
class ChatPesan extends Model
{
    protected $table = 'chat_pesan';

    protected $fillable = [
        'pengguna_a_id', 'pengguna_b_id', 'pengirim_id', 'pesan', 'pinned',
        'dibaca_at', 'dihapus_untuk_a_at', 'dihapus_untuk_b_at', 'dihapus_untuk_semua_at',
    ];

    protected $casts = [
        'pinned' => 'boolean',
        'dibaca_at' => 'datetime',
        'dihapus_untuk_a_at' => 'datetime',
        'dihapus_untuk_b_at' => 'datetime',
        'dihapus_untuk_semua_at' => 'datetime',
    ];

    public static function pasangan(int $idA, int $idB): array
    {
        return $idA < $idB ? [$idA, $idB] : [$idB, $idA];
    }

    public function penggunaA()
    {
        return $this->belongsTo(User::class, 'pengguna_a_id');
    }

    public function penggunaB()
    {
        return $this->belongsTo(User::class, 'pengguna_b_id');
    }

    public function pengirim()
    {
        return $this->belongsTo(User::class, 'pengirim_id');
    }
}
