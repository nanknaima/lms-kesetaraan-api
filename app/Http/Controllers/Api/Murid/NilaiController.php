<?php

namespace App\Http\Controllers\Api\Murid;

use App\Http\Controllers\Controller;
use App\Models\NilaiAkhir;
use App\Services\ExportPdfService;
use Illuminate\Http\Request;

class NilaiController extends Controller
{
    /** Hanya nilai yang sudah dirilis admin yang tampil ke murid (aturan bisnis #12). */
    public function index(Request $request)
    {
        $data = NilaiAkhir::where('murid_id', $request->user()->id)
            ->where('dirilis_ke_murid', true)
            ->with('mapel:id,nama_mapel')
            ->get();

        return response()->json(['data' => $data]);
    }

    public function rapor(Request $request, int $muridId, ExportPdfService $pdf)
    {
        abort_unless($request->user()->id === $muridId || $request->user()->isAdmin(), 403);

        $rows = NilaiAkhir::where('murid_id', $muridId)->where('dirilis_ke_murid', true)
            ->with('mapel:id,nama_mapel')->get()
            ->map(fn ($n) => [
                'Mapel' => $n->mapel->nama_mapel,
                'Nilai Akhir' => $n->nilai_akhir,
                'Status' => strtoupper($n->status_kelulusan),
            ])->toArray();

        return $pdf->rekapTabel('Rapor Digital', $rows, "rapor-{$muridId}.pdf");
    }
}
