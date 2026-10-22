<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'nama', 'email', 'password', 'role', 'nis', 'nip', 'status', 'avatar_url', 'google_id', 'bio', 'no_hp',
        'admin_level',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // ── Relasi ───────────────────────────────────────────────
    public function guruProfile()
    {
        return $this->hasOne(GuruProfile::class);
    }

    public function muridProfile()
    {
        return $this->hasOne(MuridProfile::class);
    }

    public function notifikasi()
    {
        return $this->hasMany(Notifikasi::class);
    }

    /**
     * @deprecated Skema interim self-claim (mobile) — lihat PenugasanGuru.
     * Sedang ditinggalkan menuju [penugasanAktif] (model Penugasan Mengajar
     * dengan alur admin approve/reject). Dipertahankan untuk saat ini supaya
     * mobile app (yang belum diaudit ulang) tidak rusak — lihat catatan
     * dual-support di ResolvesPenugasan.
     */
    public function penugasan()
    {
        return $this->hasMany(PenugasanGuru::class, 'guru_id');
    }

    /**
     * Semua penugasan mengajar guru ini yang berstatus 'disetujui' (model
     * Penugasan Mengajar — pengganti aturan lama "1 guru = 1 mapel").
     * Satu guru bisa punya banyak baris di sini, masing-masing untuk satu
     * kombinasi (kelas, mapel) spesifik.
     */
    public function penugasanAktif()
    {
        return $this->hasMany(PenugasanMengajar::class, 'guru_id')->where('status', 'disetujui');
    }

    // ── Helper role (dipakai middleware & policy) ───────────
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /** Admin tingkat 'super' — satu-satunya yang boleh kelola akun admin lain (Bagian 10). */
    public function isSuperAdmin(): bool
    {
        return $this->role === 'admin' && $this->admin_level === 'super';
    }

    public function isGuru(): bool
    {
        return $this->role === 'guru';
    }

    public function isMurid(): bool
    {
        return $this->role === 'murid';
    }

    /**
     * @deprecated Mengasumsikan "1 guru = 1 mapel" — aturan ini digantikan
     * oleh model Penugasan Mengajar (lihat [penugasanAktif]), di mana satu
     * guru bisa mengampu banyak kombinasi (kelas, mapel) spesifik yang
     * dikelola admin atau diajukan sendiri. JANGAN pakai method ini di kode
     * baru — endpoint yang sudah diaudit ke Penugasan Mengajar mengambil
     * mapel_id/kelas_id dari request + PenugasanMengajar::scopeAktif(),
     * bukan dari sini. Dipertahankan untuk endpoint lama yang belum diaudit.
     *
     * Mapel "tunggal" yang diampu guru ini — dipertahankan untuk backward
     * compat dengan Web & endpoint lama yang masih berasumsi 1 guru = 1
     * mapel (mis. dashboard Web, create Tugas/Kuis/PertemuanOnline di Web).
     * Dengan model penugasan yang baru (lihat [penugasan]) seorang guru bisa
     * memegang banyak (mapel, kelas) — method ini mengembalikan mapel-nya
     * HANYA bila semua penugasan guru itu untuk satu mapel yang sama (kasus
     * paling umum); begitu guru mengambil mapel kedua, ini sengaja jadi null
     * supaya endpoint lama menolak dengan pesan yang jelas alih-alih menebak.
     * Fallback ke guru_profile.mapel_id (skema lama) bila guru belum pernah
     * memakai sistem penugasan sama sekali.
     */
    public function mapelDiampu(): ?Mapel
    {
        $mapelIds = $this->penugasan()->distinct()->pluck('mapel_id');
        if ($mapelIds->isEmpty()) {
            return $this->guruProfile?->mapel;
        }

        return $mapelIds->count() === 1 ? Mapel::find($mapelIds->first()) : null;
    }
}
