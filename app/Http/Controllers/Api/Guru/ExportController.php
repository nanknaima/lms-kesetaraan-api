<?php

namespace App\Http\Controllers\Api\Guru;

use App\Http\Controllers\Api\Guru\Concerns\ResolvesPenugasan;
use App\Http\Controllers\Controller;
use App\Models\MuridProfile;
use App\Models\Tugas;
use App\Services\ExportExcelService;
use App\Services\ExportPdfService;
use App\Services\KomponenNilaiCalculator;
use Illuminate\Http\Request;

/**
 * Export nilai/absensi/tugas ke Excel (.xlsx) & PDF, sesuai aturan bisnis #13.
 * Menggunakan maatwebsite/excel untuk xlsx dan barryvdh/laravel-dompdf untuk PDF
 * (lihat composer.json). Preview di browser: panggil endpoint yang sama dengan
 * `?format=pdf` lalu tampilkan response (content-type application/pdf) di <iframe>.
 */
class ExportController extends Controller
{
    use ResolvesPenugasan;

    public function __construct(
        private ExportExcelService $excel,
        private ExportPdfService $pdf,
        private KomponenNilaiCalculator $komponen,
    ) {}

    /** GET /api/guru/export/tugas?tugas_id=&format=xlsx|pdf */
    public function tugas(Request $request)
    {
        $tugas = Tugas::with(['submissions.murid:id,nama,nis'])->findOrFail($request->integer('tugas_id'));
        abort_unless(
            $request->user()->isAdmin() || $tugas->guru_id === $request->user()->id,
            403
        );

        $rows = $tugas->submissions->map(fn ($s) => [
            'NIS' => $s->murid->nis,
            'Nama' => $s->murid->nama,
            'Status' => ucfirst($s->status),
            'Dikumpulkan' => optional($s->submitted_at)->format('d/m/Y H:i') ?? '-',
            'Nilai' => $s->nilai ?? '-',
            'Feedback' => $s->feedback ?? '-',
        ])->toArray();

        $judul = "Rekap Tugas: {$tugas->judul}";

        return $request->query('format') === 'pdf'
            ? $this->pdf->rekapTabel($judul, $rows, "tugas-{$tugas->id}.pdf")
            : $this->excel->rekapTabel($judul, $rows, "tugas-{$tugas->id}.xlsx");
    }

    /**
     * GET /api/guru/export/nilai?format=xlsx|pdf — rekap nilai komponen x
     * murid mapel yang diampu guru (Bagian 5 — kolom komponen dinamis sesuai
     * komponen_nilai yang diatur guru per mapel, bukan lagi 4 kolom tetap).
     */
    public function nilai(Request $request)
    {
        ['mapel_id' => $mapelId, 'kelas_id' => $kelasId] = $this->resolvePenugasan($request);

        $muridIds = MuridProfile::where('kelas_id', $kelasId)->pluck('user_id');
        $murid = \App\Models\User::whereIn('id', $muridIds)->get(['id', 'nama', 'nis'])->keyBy('id');

        $hasil = $this->komponen->matriks($mapelId, $muridIds);

        $rows = collect($hasil['baris'])->map(function ($b) use ($murid, $hasil) {
            $m = $murid->get($b['murid_id']);
            $row = ['NIS' => $m?->nis, 'Nama' => $m?->nama];
            foreach ($hasil['komponen'] as $k) {
                $row[$k->nama] = $b['nilai_per_komponen'][$k->id] ?? '-';
            }
            $row['Nilai Akhir'] = $b['nilai_akhir'];

            return $row;
        })->toArray();

        $judul = 'Rekap Nilai Akhir';

        return $request->query('format') === 'pdf'
            ? $this->pdf->rekapTabel($judul, $rows, 'nilai-akhir.pdf')
            : $this->excel->rekapTabel($judul, $rows, 'nilai-akhir.xlsx');
    }

    /** GET /api/guru/export/absensi?format=xlsx|pdf&kelas_id=&bulan= */
    public function absensi(Request $request)
    {
        $mapelId = $this->resolveMapelId($request);
        $bulan = $request->query('bulan', now()->format('Y-m'));

        $rows = \App\Models\Absensi::where('mapel_id', $mapelId)
            ->where('tanggal', 'like', "{$bulan}%")
            ->with('murid:id,nama,nis')
            ->get()
            ->groupBy('murid_id')
            ->map(function ($rows, $muridId) {
                $murid = $rows->first()->murid;
                return [
                    'NIS' => $murid->nis,
                    'Nama' => $murid->nama,
                    'Hadir' => $rows->where('status', 'hadir')->count(),
                    'Izin' => $rows->where('status', 'izin')->count(),
                    'Sakit' => $rows->where('status', 'sakit')->count(),
                    'Alpa' => $rows->where('status', 'alpa')->count(),
                ];
            })->values()->toArray();

        $judul = "Rekap Absensi Bulan {$bulan}";

        return $request->query('format') === 'pdf'
            ? $this->pdf->rekapTabel($judul, $rows, "absensi-{$bulan}.pdf")
            : $this->excel->rekapTabel($judul, $rows, "absensi-{$bulan}.xlsx");
    }
}
