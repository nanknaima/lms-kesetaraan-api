<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pendaftaran;
use App\Services\ExportExcelService;
use App\Services\ExportPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PendaftaranController extends Controller
{
    public function __construct(private ExportExcelService $excel, private ExportPdfService $pdf) {}

    /** GET /api/admin/pendaftaran?status= */
    public function index(Request $request)
    {
        $data = Pendaftaran::when($request->status, fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json(['data' => $data]);
    }

    /** GET /api/admin/pendaftaran/{pendaftaran} — dipakai buat prefill form Tambah Murid. */
    public function show(Pendaftaran $pendaftaran)
    {
        return response()->json(['data' => $pendaftaran]);
    }

    /**
     * POST /api/admin/pendaftaran/{pendaftaran}/approve
     * Cuma menandai status disetujui — TIDAK membuat akun murid otomatis lagi
     * (aturan bisnis #3 & #10/J): NIS & password sekarang input manual admin
     * lewat menu Tambah Murid (Admin\MuridController::store()), yang bisa
     * di-prefill dari data pendaftaran ini via ?dari_pendaftaran={id}.
     */
    public function approve(Request $request, Pendaftaran $pendaftaran)
    {
        abort_if($pendaftaran->status !== 'pending', 422, 'Pendaftaran sudah diproses.');

        $pendaftaran->update([
            'status' => 'approved',
            'diverifikasi_oleh' => $request->user()->id,
        ]);

        return response()->json(['data' => $pendaftaran->fresh()]);
    }

    /** POST /api/admin/pendaftaran/{pendaftaran}/reject */
    public function reject(Request $request, Pendaftaran $pendaftaran)
    {
        $validator = Validator::make($request->all(), ['alasan' => ['required', 'string']]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid.', 'errors' => $validator->errors()], 422);
        }

        $pendaftaran->update([
            'status' => 'rejected',
            'alasan_reject' => $request->alasan,
            'diverifikasi_oleh' => $request->user()->id,
        ]);

        return response()->json(['data' => $pendaftaran]);
    }

    /** GET /api/admin/pendaftaran/export?format=pdf|xlsx&status= — rekap pendaftar untuk laporan (Bagian G5). */
    public function export(Request $request)
    {
        $rows = Pendaftaran::when($request->status, fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->get()
            ->map(fn (Pendaftaran $p) => [
                'Nama' => $p->nama,
                'Email' => $p->email,
                'No. HP' => $p->no_hp,
                'Paket' => $p->paket,
                'Tanggal Daftar' => $p->created_at->translatedFormat('d F Y'),
                'Status' => match ($p->status) {
                    'approved' => 'Disetujui',
                    'rejected' => 'Ditolak',
                    default => 'Menunggu',
                },
            ])->toArray();

        $judul = 'Rekap Pendaftaran Murid Baru';

        return $request->query('format') === 'pdf'
            ? $this->pdf->rekapTabel($judul, $rows, 'rekap-pendaftaran.pdf')
            : $this->excel->rekapTabel($judul, $rows, 'rekap-pendaftaran.xlsx');
    }
}
