<?php

namespace App\Http\Controllers\Api\Guru;

use App\Http\Controllers\Api\Guru\Concerns\ResolvesPenugasan;
use App\Http\Controllers\Controller;
use App\Models\NilaiAkhir;
use App\Services\NilaiAkhirCalculatorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class NilaiAkhirController extends Controller
{
    use ResolvesPenugasan;

    public function __construct(private NilaiAkhirCalculatorService $calculator) {}

    public function index(Request $request)
    {
        $mapelId = $this->resolveMapelId($request);

        return response()->json(['data' => NilaiAkhir::where('mapel_id', $mapelId)
            ->with('murid:id,nama,nis')->paginate(30)]);
    }

    /** Trigger auto-kalkulasi nilai akhir murid untuk mapel yang diampu guru ini. */
    public function hitung(Request $request, int $muridId)
    {
        $mapelId = $this->resolveMapelId($request);

        $nilaiAkhir = $this->calculator->hitung($muridId, $mapelId);

        return response()->json(['data' => $nilaiAkhir]);
    }

    /** Guru override nilai/catatan + beri rekomendasi lulus/tidak. */
    public function update(Request $request, NilaiAkhir $nilaiAkhir)
    {
        $this->authorizeMapel($request, $nilaiAkhir);

        $validator = Validator::make($request->all(), [
            'nilai_akhir' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'catatan_guru' => ['nullable', 'string'],
            'rekomendasi_guru_lulus' => ['required', 'boolean'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid.', 'errors' => $validator->errors()], 422);
        }

        $nilaiAkhir->update($validator->validated());

        return response()->json(['data' => $nilaiAkhir]);
    }

    /** Kirim ke admin untuk finalisasi kelulusan (aturan bisnis #12). */
    public function kirimAdmin(Request $request, NilaiAkhir $nilaiAkhir)
    {
        $this->authorizeMapel($request, $nilaiAkhir);
        abort_if(is_null($nilaiAkhir->rekomendasi_guru_lulus), 422, 'Isi rekomendasi lulus/tidak terlebih dahulu.');
        abort_if(! is_null($nilaiAkhir->dikirim_ke_admin_at), 422, 'Nilai murid ini sudah pernah dikirim ke admin.');

        $nilaiAkhir->update(['status_kelulusan' => 'pending', 'dikirim_ke_admin_at' => now()]);

        return response()->json(['message' => 'Rekomendasi dikirim ke admin untuk validasi.', 'data' => $nilaiAkhir]);
    }

    /**
     * POST /guru/nilai-akhir/kirim-admin-massal?mapel_id= — kirim SEMUA
     * murid yang sudah punya rekomendasi lulus/tidak DAN belum pernah dikirim
     * sekaligus, dalam satu transaction. Murid yang rekomendasinya belum
     * diisi atau yang sudah pernah dikirim dilewati (dilaporkan beserta
     * alasannya) — bukan error, supaya guru tetap bisa kirim sisanya.
     */
    public function kirimAdminMassal(Request $request)
    {
        $mapelId = $this->resolveMapelId($request);

        $daftar = NilaiAkhir::where('mapel_id', $mapelId)->with('murid:id,nama,nis')->get();

        $terkirim = [];
        $dilewati = [];

        DB::transaction(function () use ($daftar, &$terkirim, &$dilewati) {
            foreach ($daftar as $n) {
                $nama = $n->murid?->nama ?? "Murid #{$n->murid_id}";

                if (is_null($n->rekomendasi_guru_lulus)) {
                    $dilewati[] = ['nama' => $nama, 'alasan' => 'Rekomendasi lulus/tidak lulus belum diisi'];

                    continue;
                }
                // Sudah pernah dikirim ATAU sudah selesai diverifikasi admin —
                // kedua kondisi ini berarti murid sudah melewati alur approval,
                // jangan diganggu (status_kelulusan-nya jangan direset ke pending lagi).
                if (! is_null($n->dikirim_ke_admin_at) || $n->verified_by_admin) {
                    $dilewati[] = ['nama' => $nama, 'alasan' => 'Sudah pernah dikirim/diverifikasi admin sebelumnya'];

                    continue;
                }

                $n->update(['status_kelulusan' => 'pending', 'dikirim_ke_admin_at' => now()]);
                $terkirim[] = $nama;
            }
        });

        $jumlahTerkirim = count($terkirim);
        $message = $jumlahTerkirim > 0
            ? "{$jumlahTerkirim} murid berhasil dikirim ke admin."
            : 'Tidak ada murid yang terkirim.';

        return response()->json([
            'message' => $message,
            'jumlah_terkirim' => $jumlahTerkirim,
            'terkirim' => $terkirim,
            'jumlah_dilewati' => count($dilewati),
            'dilewati' => $dilewati,
        ]);
    }
}
