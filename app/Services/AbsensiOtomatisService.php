<?php

namespace App\Services;

use App\Models\Absensi;
use App\Models\PertemuanOnline;
use App\Models\User;

/**
 * Menandai murid "hadir" secara otomatis saat ia terlihat aktif di suatu
 * mapel hari ini (unduh materi, menonton video sampai selesai, berkomentar
 * di diskusi) — dipanggil dari beberapa controller berbeda, lihat pemanggil.
 * Sengaja pakai firstOrCreate (bukan updateOrCreate): kalau guru sudah input
 * status manual untuk tanggal itu (mis. "izin"), aktivitas otomatis TIDAK
 * menimpanya — hanya mengisi kalau baris untuk (mapel, murid, tanggal) itu
 * belum ada sama sekali.
 */
class AbsensiOtomatisService
{
    public function tandaiHadir(User $murid, int $mapelId, string $keterangan): void
    {
        Absensi::firstOrCreate(
            ['mapel_id' => $mapelId, 'murid_id' => $murid->id, 'tanggal' => now()->toDateString()],
            ['status' => 'hadir', 'keterangan' => $keterangan, 'otomatis' => true]
        );
    }

    /**
     * Absensi saat murid BERHASIL join pertemuan online — aturan sedikit beda dari
     * tandaiHadir() di atas, makanya method terpisah (materi/diskusi tetap pakai yang lama):
     *  - tanggal = tanggal PERTEMUAN (bukan hari ini; join bisa 10 menit sebelum mulai,
     *    lintas tengah malam).
     *  - belum ada baris        -> dibuat 'hadir'.
     *  - sudah 'izin'/'sakit'   -> TIDAK ditimpa (alasan manual murid dihormati).
     *  - sudah 'alpa'           -> dinaikkan jadi 'hadir' (murid ternyata hadir).
     *  - sudah 'hadir'          -> dibiarkan (idempotent, aman join berkali-kali).
     * Semua kegagalan ditelan (dilaporkan ke log) — absensi TIDAK BOLEH menggagalkan join.
     */
    public function tandaiHadirPertemuan(User $murid, PertemuanOnline $pertemuan): void
    {
        try {
            $keterangan = "Hadir otomatis via Pertemuan Online: {$pertemuan->judul}";

            $absensi = Absensi::firstOrCreate(
                [
                    'mapel_id' => $pertemuan->mapel_id,
                    'murid_id' => $murid->id,
                    'tanggal' => $pertemuan->tanggal->toDateString(),
                ],
                ['status' => 'hadir', 'keterangan' => $keterangan, 'otomatis' => true]
            );

            if (! $absensi->wasRecentlyCreated && $absensi->status === 'alpa') {
                $absensi->update(['status' => 'hadir', 'keterangan' => $keterangan, 'otomatis' => true]);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
