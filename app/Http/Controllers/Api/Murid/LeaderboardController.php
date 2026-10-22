<?php

namespace App\Http\Controllers\Api\Murid;

use App\Http\Controllers\Controller;
use App\Models\JawabanKuis;
use App\Models\MuridProfile;
use Illuminate\Http\Request;

/**
 * Papan peringkat kuis per kelas (Bagian E) — "poin" didefinisikan dari
 * konsep yang SUDAH ADA di skema: SUM(jawaban_kuis.skor) milik murid, yaitu
 * akumulasi skor semua jawaban kuis yang sudah dia kerjakan (skor per soal =
 * SoalKuis::poin kalau benar, lihat KuisKerjakanController::submit()) — bukan
 * konsep baru, cuma diagregasi & dirangking di sini. Dibatasi ke kuis milik
 * kelas murid sendiri (Kuis::kelas_id) supaya perbandingan adil: semua murid
 * yang dirangking mengerjakan kuis yang sama.
 */
class LeaderboardController extends Controller
{
    public function index(Request $request)
    {
        $murid = $request->user();
        $profile = $murid->muridProfile;
        abort_if(! $profile || ! $profile->kelas_id, 422, 'Murid belum terdaftar di kelas manapun.');

        $kelasId = $profile->kelas_id;

        $poinPerMurid = JawabanKuis::query()
            ->join('kuis', 'kuis.id', '=', 'jawaban_kuis.kuis_id')
            ->where('kuis.kelas_id', $kelasId)
            ->selectRaw('jawaban_kuis.murid_id as murid_id, SUM(jawaban_kuis.skor) as total_poin')
            ->groupBy('jawaban_kuis.murid_id')
            ->pluck('total_poin', 'murid_id');

        // Sertakan SEMUA murid sekelas (bukan cuma yang sudah pernah kuis) supaya
        // peringkat mencerminkan seluruh teman sekelas, yang belum ikut kuis = 0 poin.
        $temanSekelas = MuridProfile::where('kelas_id', $kelasId)
            ->with('user:id,nama,avatar_url')
            ->get()
            ->map(fn (MuridProfile $p) => [
                'murid_id' => $p->user_id,
                'nama' => $p->user?->nama ?? '-',
                'avatar_url' => $p->user?->avatar_url,
                'poin' => (int) ($poinPerMurid[$p->user_id] ?? 0),
            ])
            ->sortByDesc('poin')
            ->values();

        // Dense rank: peringkat sama untuk poin yang sama, tidak "melompat" nomor.
        $peringkat = 0;
        $poinSebelumnya = null;
        $withRank = $temanSekelas->map(function (array $row, int $i) use (&$peringkat, &$poinSebelumnya) {
            if ($row['poin'] !== $poinSebelumnya) {
                $peringkat = $i + 1;
                $poinSebelumnya = $row['poin'];
            }

            return [...$row, 'peringkat' => $peringkat];
        });

        $saya = $withRank->firstWhere('murid_id', $murid->id);

        return response()->json([
            'data' => [
                'poin_saya' => $saya['poin'] ?? 0,
                'peringkat_saya' => $saya['peringkat'] ?? null,
                'total_murid' => $withRank->count(),
                'top' => $withRank->take(10)->values(),
            ],
        ]);
    }
}
