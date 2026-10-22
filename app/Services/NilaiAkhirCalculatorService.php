<?php

namespace App\Services;

use App\Models\BobotNilaiConfig;
use App\Models\KomponenNilai;
use App\Models\NilaiAkhir;

/**
 * Nilai Akhir = Σ(nilai_komponen_efektif × bobot_persen/100) dari seluruh
 * komponen_nilai mapel itu (lihat Bagian 5 — komponen fleksibel yang diatur
 * guru sendiri per mapel, menggantikan bobot tugas/kuis/absensi/ujian yang
 * kaku). Nilai KUIS TIDAK PERNAH ikut dihitung — kuis murni latihan murid.
 *
 * `passing_grade` masih dibaca dari BobotNilaiConfig (mapel-spesifik atau
 * default global) — field itu tidak terkait sistem bobot lama yang
 * dihilangkan, jadi tetap dipakai apa adanya daripada bikin tabel baru
 * hanya untuk satu angka ambang kelulusan.
 */
class NilaiAkhirCalculatorService
{
    public function __construct(private KomponenNilaiCalculator $komponen) {}

    public function hitung(int $muridId, int $mapelId): NilaiAkhir
    {
        $totalBobot = (float) KomponenNilai::where('mapel_id', $mapelId)->sum('bobot_persen');
        abort_if($totalBobot <= 0, 422, 'Belum ada komponen nilai untuk mapel ini. Atur komponen nilai terlebih dahulu di menu Kelola Komponen Nilai.');
        abort_if(
            abs($totalBobot - 100) > 0.01,
            422,
            "Total bobot komponen nilai harus 100% (saat ini {$totalBobot}%). Perbaiki di menu Kelola Komponen Nilai."
        );

        $hasil = $this->komponen->hitungAkhir($mapelId, $muridId);

        $config = BobotNilaiConfig::where('mapel_id', $mapelId)->first()
            ?? BobotNilaiConfig::whereNull('mapel_id')->first();
        $passingGrade = $config->passing_grade ?? 75;

        $rincian = collect($hasil['rincian']);
        $nilaiTugas = $rincian->firstWhere('tipe', 'auto_tugas')['nilai'] ?? null;
        $nilaiAbsensi = $rincian->firstWhere('tipe', 'auto_absensi')['nilai'] ?? null;

        return NilaiAkhir::updateOrCreate(
            ['murid_id' => $muridId, 'mapel_id' => $mapelId],
            [
                'nilai_tugas' => $nilaiTugas,
                'nilai_kuis' => null, // kuis tidak lagi berkontribusi ke nilai akademik
                'nilai_absensi' => $nilaiAbsensi,
                'nilai_ujian' => null, // "ujian" kini jadi salah satu komponen fleksibel biasa, lihat rincian_komponen
                'rincian_komponen' => $hasil['rincian'],
                'nilai_akhir' => $hasil['nilai_akhir'],
                'rekomendasi_guru_lulus' => $hasil['nilai_akhir'] >= $passingGrade,
            ]
        );
    }
}
