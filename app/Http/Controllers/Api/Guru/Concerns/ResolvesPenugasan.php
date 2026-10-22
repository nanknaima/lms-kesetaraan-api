<?php

namespace App\Http\Controllers\Api\Guru\Concerns;

use App\Models\PenugasanGuru;
use App\Models\PenugasanMengajar;
use Illuminate\Http\Request;

/**
 * Dipakai oleh controller yang membuat/mengelola konten guru (Materi, Tugas,
 * Kuis, PertemuanOnline, Absensi, NilaiAkhir, Export) supaya mendukung TIGA
 * jalur sekaligus (dual-support sementara, sampai mobile diaudit ke skema
 * baru di prompt terpisah):
 *   - Mobile (interim self-claim): request kirim `penugasan_id` — dicek ke
 *     tabel penugasan_guru (lihat Api\Guru\PenugasanController). TIDAK
 *     disentuh sama sekali supaya mobile app yang sudah jalan tidak rusak.
 *   - Web (BARU, model Penugasan Mengajar): request kirim `kelas_id` DAN
 *     `mapel_id` eksplisit (dipilih dari kartu penugasan di dashboard) —
 *     dicek aktif di penugasan_mengajar ATAU penugasan_guru (dual-check,
 *     supaya guru yang kombinasi aktifnya kebetulan cuma tercatat di salah
 *     satu tabel tetap lolos).
 *   - Web (LAMA, sebelum Tahap 6 diterapkan): request cuma kirim `kelas_id`
 *     tanpa `mapel_id` — mapel_id tetap diambil implisit dari mapelDiampu()
 *     (fallback single-mapel), TIDAK diubah.
 */
trait ResolvesPenugasan
{
    /** Guru login punya penugasan AKTIF untuk (kelas_id, mapel_id) ini — di penugasan_mengajar (baru) ATAU penugasan_guru (interim). */
    private function adaPenugasanAktifUntuk(Request $request, int $kelasId, int $mapelId): bool
    {
        $guruId = $request->user()->id;

        return PenugasanMengajar::aktif()->where('guru_id', $guruId)->where('kelas_id', $kelasId)->where('mapel_id', $mapelId)->exists()
            || PenugasanGuru::where('guru_id', $guruId)->where('kelas_id', $kelasId)->where('mapel_id', $mapelId)->exists();
    }

    /** Guru login punya penugasan AKTIF untuk mapel_id ini (kelas apa saja) — di penugasan_mengajar (baru) ATAU penugasan_guru (interim). */
    private function adaPenugasanAktifMapel(Request $request, int $mapelId): bool
    {
        $guruId = $request->user()->id;

        return PenugasanMengajar::aktif()->where('guru_id', $guruId)->where('mapel_id', $mapelId)->exists()
            || PenugasanGuru::where('guru_id', $guruId)->where('mapel_id', $mapelId)->exists();
    }

    /** @return array{mapel_id: int, kelas_id: int} */
    protected function resolvePenugasan(Request $request): array
    {
        if ($request->filled('penugasan_id')) {
            $penugasan = PenugasanGuru::find($request->input('penugasan_id'));
            abort_if(! $penugasan, 422, 'Penugasan tidak ditemukan.');
            abort_unless($penugasan->guru_id === $request->user()->id, 403, 'Penugasan ini bukan milik Anda.');

            return ['mapel_id' => $penugasan->mapel_id, 'kelas_id' => $penugasan->kelas_id];
        }

        if ($request->filled('kelas_id') && $request->filled('mapel_id')) {
            $kelasId = (int) $request->input('kelas_id');
            $mapelId = (int) $request->input('mapel_id');
            abort_unless(
                $this->adaPenugasanAktifUntuk($request, $kelasId, $mapelId),
                403,
                'Anda tidak memiliki penugasan aktif untuk kelas & mapel ini.'
            );

            return ['mapel_id' => $mapelId, 'kelas_id' => $kelasId];
        }

        $mapelId = $request->user()->mapelDiampu()?->id;
        abort_if(
            ! $mapelId,
            422,
            'Guru mengampu lebih dari satu mata pelajaran — pilih penugasan (mapel & kelas) lewat dashboard.'
        );
        abort_unless($request->filled('kelas_id'), 422, 'kelas_id wajib diisi.');

        return ['mapel_id' => $mapelId, 'kelas_id' => (int) $request->input('kelas_id')];
    }

    /**
     * Sama seperti [resolvePenugasan] tapi hanya butuh mapel_id (dipakai
     * Absensi/NilaiAkhir/Export yang tidak per-kelas) — kalau guru kirim
     * `mapel_id` langsung, pakai itu asal mapel itu memang salah satu yang
     * diampunya (dicek dual: penugasan_mengajar ATAU penugasan_guru);
     * kalau tidak, fallback ke mapelDiampu() (jalur lama, Web single-mapel).
     */
    protected function resolveMapelId(Request $request): int
    {
        if ($request->filled('mapel_id')) {
            $mapelId = (int) $request->input('mapel_id');
            abort_unless($this->adaPenugasanAktifMapel($request, $mapelId), 403, 'Anda tidak mengampu mapel ini.');

            return $mapelId;
        }

        $mapelId = $request->user()->mapelDiampu()?->id;
        abort_if(
            ! $mapelId,
            422,
            'Guru mengampu lebih dari satu mata pelajaran — sertakan mapel_id lewat dashboard.'
        );

        return $mapelId;
    }

    /**
     * Cek guru login memang mengampu mapel_id milik $model (NilaiAkhir dsb)
     * — dual-check penugasan_mengajar (baru) ATAU penugasan_guru (interim).
     */
    protected function authorizeMapel(Request $request, $model): void
    {
        abort_unless($this->adaPenugasanAktifMapel($request, $model->mapel_id), 403, 'Anda tidak mengampu mapel ini.');
    }
}
