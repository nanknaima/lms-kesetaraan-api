<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notifikasi extends Model
{
    /**
     * Kirim notifikasi in-app ke SEMUA murid di sebuah kelas (dipakai saat guru membuat
     * pertemuan online / kuis / tugas). Kegagalan hanya dilaporkan ke log — notifikasi
     * tidak boleh menggagalkan aksi guru yang memicunya.
     */
    public static function kirimKeKelas(int $kelasId, string $tipe, string $judul, string $pesan, ?string $link = null): void
    {
        try {
            $muridIds = MuridProfile::where('kelas_id', $kelasId)->pluck('user_id');
            $now = now();
            self::insert($muridIds->map(fn ($uid) => [
                'user_id' => $uid, 'tipe' => $tipe, 'judul' => $judul, 'pesan' => $pesan,
                'link' => $link, 'is_read' => false, 'created_at' => $now,
            ])->all());
        } catch (\Throwable $e) {
            report($e);
        }
    }

    protected $table = 'notifikasi';
    public $timestamps = false;
    protected $fillable = ['user_id', 'tipe', 'judul', 'pesan', 'link', 'is_read', 'created_at'];
    protected $casts = ['created_at' => 'datetime', 'is_read' => 'boolean'];
}
