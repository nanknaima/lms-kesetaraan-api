<?php

namespace App\Services;

use App\Models\Absensi;
use App\Models\KomponenNilai;
use App\Models\NilaiKomponenMurid;
use App\Models\SubmissionTugas;
use Illuminate\Support\Collection;

/**
 * Menghitung nilai efektif tiap komponen_nilai (lihat Bagian 5 — pengganti
 * bobot tugas/kuis/absensi/ujian yang kaku dengan komponen fleksibel yang
 * diatur guru sendiri per mapel). Dipakai bareng oleh endpoint matriks nilai
 * (tampilan spreadsheet guru) dan NilaiAkhirCalculatorService (finalisasi).
 */
class KomponenNilaiCalculator
{
    /** Nilai efektif satu komponen untuk satu murid — null kalau belum ada data sama sekali. */
    public function nilaiUntuk(KomponenNilai $komponen, int $muridId): ?float
    {
        return match ($komponen->tipe) {
            'manual' => NilaiKomponenMurid::where('komponen_nilai_id', $komponen->id)
                ->where('murid_id', $muridId)->value('nilai'),
            'auto_tugas' => $this->rataTugas($komponen->mapel_id, $muridId),
            'auto_absensi' => $this->persenAbsensi($komponen->mapel_id, $muridId),
        };
    }

    public function rataTugas(int $mapelId, int $muridId): ?float
    {
        $avg = SubmissionTugas::whereHas('tugas', fn ($q) => $q->where('mapel_id', $mapelId))
            ->where('murid_id', $muridId)->whereNotNull('nilai')->avg('nilai');

        return $avg !== null ? round((float) $avg, 2) : null;
    }

    public function persenAbsensi(int $mapelId, int $muridId): ?float
    {
        $total = Absensi::where('mapel_id', $mapelId)->where('murid_id', $muridId)->count();
        if ($total === 0) {
            return null;
        }
        $hadir = Absensi::where('mapel_id', $mapelId)->where('murid_id', $muridId)->where('status', 'hadir')->count();

        return round(($hadir / $total) * 100, 2);
    }

    /**
     * Rincian + nilai akhir satu murid untuk semua komponen_nilai aktif mapel
     * ini. Komponen tanpa data sama sekali dihitung sebagai 0 (bukan
     * diabaikan) supaya bobotnya tetap berpengaruh — konsisten dengan
     * perilaku formula lama (nilai kosong = 0).
     *
     * @return array{nilai_akhir: float, rincian: array<int, array>, bobot_total: float}
     */
    public function hitungAkhir(int $mapelId, int $muridId): array
    {
        $komponen = KomponenNilai::where('mapel_id', $mapelId)->orderBy('urutan')->get();
        $bobotTotal = (float) $komponen->sum('bobot_persen');

        $akhir = 0.0;
        $rincian = $komponen->map(function (KomponenNilai $k) use ($muridId, &$akhir) {
            $nilai = $this->nilaiUntuk($k, $muridId) ?? 0.0;
            $akhir += $nilai * (float) $k->bobot_persen / 100;

            return [
                'komponen_nilai_id' => $k->id,
                'nama' => $k->nama,
                'tipe' => $k->tipe,
                'bobot_persen' => (float) $k->bobot_persen,
                'nilai' => round($nilai, 2),
            ];
        })->all();

        return ['nilai_akhir' => round($akhir, 2), 'rincian' => $rincian, 'bobot_total' => $bobotTotal];
    }

    /** Matriks murid x komponen untuk tampilan spreadsheet guru — lihat Guru\KomponenNilaiController::matriks(). */
    public function matriks(int $mapelId, Collection $muridIds): array
    {
        $komponen = KomponenNilai::where('mapel_id', $mapelId)->orderBy('urutan')->get();

        $baris = $muridIds->map(function (int $muridId) use ($komponen) {
            $hasil = $this->hitungAkhirDariKomponen($komponen, $muridId);

            return ['murid_id' => $muridId] + $hasil;
        })->values()->all();

        return ['komponen' => $komponen, 'baris' => $baris, 'bobot_total' => (float) $komponen->sum('bobot_persen')];
    }

    private function hitungAkhirDariKomponen(Collection $komponen, int $muridId): array
    {
        $akhir = 0.0;
        $nilaiPerKomponen = [];
        foreach ($komponen as $k) {
            $nilai = $this->nilaiUntuk($k, $muridId);
            $akhir += ($nilai ?? 0.0) * (float) $k->bobot_persen / 100;
            $nilaiPerKomponen[$k->id] = $nilai;
        }

        return ['nilai_per_komponen' => $nilaiPerKomponen, 'nilai_akhir' => round($akhir, 2)];
    }
}
