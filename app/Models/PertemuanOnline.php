<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PertemuanOnline extends Model
{
    protected $table = 'pertemuan_online';
    protected $fillable = [
        'mapel_id', 'kelas_id', 'guru_id', 'judul', 'link_meeting', 'platform',
        'tanggal', 'jam_mulai', 'jam_selesai', 'agenda', 'status',
        'reminder_h1_sent', 'reminder_15m_sent',
    ];
    protected $casts = ['tanggal' => 'date'];

    public function mapel()
    {
        return $this->belongsTo(Mapel::class);
    }

    public function kelas()
    {
        return $this->belongsTo(Kelas::class);
    }

    public function guru()
    {
        return $this->belongsTo(User::class, 'guru_id');
    }

    /** Waktu mulai sebagai Carbon lengkap (tanggal + jam_mulai). */
    public function waktuMulai(): \Illuminate\Support\Carbon
    {
        return \Illuminate\Support\Carbon::parse($this->tanggal->format('Y-m-d').' '.$this->jam_mulai);
    }

    /** Waktu selesai sebagai Carbon lengkap (tanggal + jam_selesai). */
    public function waktuSelesai(): \Illuminate\Support\Carbon
    {
        return \Illuminate\Support\Carbon::parse($this->tanggal->format('Y-m-d').' '.$this->jam_selesai);
    }

    /**
     * Aktif dari 10 menit sebelum mulai SAMPAI waktu selesai — sebelumnya cuma
     * mengecek batas bawah (>= mulai - 10 menit) dan tidak pernah mengecek batas
     * atas, jadi tombol join tetap "aktif" selamanya setelah jam_selesai lewat
     * selama status masih 'terjadwal'. Ditemukan lewat laporan bug tombol join
     * murid yang tetap "Belum Aktif" — akar masalah sebenarnya ada di frontend
     * yang tidak polling ulang (lihat PertemuanOnlineCard & dashboard murid/guru),
     * tapi celah batas atas ini juga nyata dan diperbaiki sekalian.
     */
    public function joinAktif(): bool
    {
        $sekarang = now();

        return $sekarang->greaterThanOrEqualTo($this->waktuMulai()->subMinutes(10))
            && $sekarang->lessThanOrEqualTo($this->waktuSelesai())
            && $this->status === 'terjadwal';
    }

    /**
     * Sudah lewat jam_selesai — dipakai frontend buat bedakan label tombol
     * "Belum Aktif" (belum waktunya) vs "Sudah Selesai" (waktunya sudah lewat).
     * Dihitung di backend (bukan di-parse ulang di frontend dari string
     * tanggal+jam) supaya tidak ada risiko salah timezone dobel di dua tempat.
     */
    public function sudahSelesai(): bool
    {
        return now()->greaterThan($this->waktuSelesai());
    }

    /**
     * SATU-SATUNYA tempat status waktu (join_aktif, sudah_selesai) ditempelkan ke
     * response API — dipakai index() guru, upcoming() guru+murid. Jangan hitung
     * ulang di controller lain supaya guru & murid tidak bisa out-of-sync lagi.
     */
    public function toApiArray(): array
    {
        return [
            ...$this->toArray(),
            'join_aktif' => $this->joinAktif(),
            'sudah_selesai' => $this->sudahSelesai(),
        ];
    }
}
