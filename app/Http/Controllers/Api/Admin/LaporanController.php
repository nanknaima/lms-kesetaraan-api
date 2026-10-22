<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\ExportExcelService;
use App\Services\ExportPdfService;
use Illuminate\Http\Request;

/** Laporan keseluruhan (guru/murid/kelulusan) — pola sama dengan Guru\ExportController::tugas(). */
class LaporanController extends Controller
{
    public function __construct(private ExportExcelService $excel, private ExportPdfService $pdf) {}

    /**
     * GET /admin/export/laporan/count?jenis= — jumlah baris yang akan ikut
     * terunduh, dipakai frontend untuk badge di kartu laporan SEBELUM admin
     * klik unduh (aturan bisnis #9), supaya laporan kelulusan yang kosong
     * (belum ada nilai dirilis) tidak mengejutkan admin setelah diunduh.
     */
    public function count(Request $request)
    {
        $jenis = $request->query('jenis', 'murid');
        $count = match ($jenis) {
            'guru' => \App\Models\User::where('role', 'guru')->count(),
            'kelulusan' => \App\Models\NilaiAkhir::where('dirilis_ke_murid', true)->count(),
            default => \App\Models\User::where('role', 'murid')->count(),
        };

        return response()->json(['jenis' => $jenis, 'count' => $count]);
    }

    /** GET /admin/export/laporan/preview?jenis= — isi tabel yang sama dengan export, buat dilihat dulu sebelum diunduh (Bagian H). */
    public function preview(Request $request)
    {
        $jenis = $request->query('jenis', 'murid');

        return response()->json(['jenis' => $jenis, 'data' => $this->rowsFor($jenis)]);
    }

    public function export(Request $request)
    {
        $jenis = $request->query('jenis', 'murid'); // guru|murid|kelulusan
        $rows = $this->rowsFor($jenis);
        $judul = 'Laporan '.ucfirst($jenis);

        return $request->query('format') === 'pdf'
            ? $this->pdf->rekapTabel($judul, $rows, "laporan-{$jenis}.pdf")
            : $this->excel->rekapTabel($judul, $rows, "laporan-{$jenis}.xlsx");
    }

    /** Dipakai bareng oleh preview() (JSON) & export() (file) supaya isinya selalu sinkron. */
    private function rowsFor(string $jenis): array
    {
        return match ($jenis) {
            'guru' => \App\Models\User::where('role', 'guru')->with('guruProfile.mapel')->get()
                ->map(fn ($g) => [
                    'NIP' => $g->nip, 'Nama' => $g->nama, 'Mapel' => $g->guruProfile?->mapel?->nama_mapel, 'Status' => $g->status,
                ])->toArray(),
            'kelulusan' => \App\Models\NilaiAkhir::with('murid:id,nama,nis')->where('dirilis_ke_murid', true)->get()
                ->map(fn ($n) => [
                    'NIS' => $n->murid->nis, 'Nama' => $n->murid->nama, 'Nilai Akhir' => $n->nilai_akhir, 'Status' => $n->status_kelulusan,
                ])->toArray(),
            default => \App\Models\User::where('role', 'murid')->with('muridProfile.kelas')->get()
                ->map(fn ($m) => [
                    'NIS' => $m->nis, 'Nama' => $m->nama, 'Paket' => $m->muridProfile?->paket,
                    'Kelas' => $m->muridProfile?->kelas?->nama_kelas, 'Status' => $m->status,
                ])->toArray(),
        };
    }
}
