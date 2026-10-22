<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\NilaiAkhir;
use Illuminate\Http\Request;

/** Validasi kelulusan oleh admin (aturan bisnis #12: kelulusan wajib divalidasi admin). */
class KelulusanController extends Controller
{
    public function index(Request $request)
    {
        $data = NilaiAkhir::with('murid:id,nama,nis', 'mapel:id,nama_mapel')
            ->where('verified_by_admin', false)
            ->whereNotNull('rekomendasi_guru_lulus')
            ->paginate($request->integer('per_page', 20));

        return response()->json(['data' => $data]);
    }

    public function verifikasi(Request $request, NilaiAkhir $nilaiAkhir)
    {
        $approve = $request->boolean('approve', true);

        $nilaiAkhir->update([
            'status_kelulusan' => $approve
                ? ($nilaiAkhir->rekomendasi_guru_lulus ? 'lulus' : 'tidak_lulus')
                : 'pending',
            'verified_by_admin' => $approve,
            'verified_by' => $request->user()->id,
            'dirilis_ke_murid' => $approve,
        ]);

        if ($approve) {
            $nilaiAkhir->murid->muridProfile()->update(['status_kelulusan' => $nilaiAkhir->status_kelulusan]);
        }

        return response()->json(['data' => $nilaiAkhir]);
    }
}
