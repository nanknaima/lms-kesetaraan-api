<?php

namespace App\Http\Controllers\Api\Murid;

use App\Http\Controllers\Controller;
use App\Models\Materi;
use App\Models\MateriReaksi;
use App\Services\AbsensiOtomatisService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MateriDownloadController extends Controller
{
    public function __construct(private AbsensiOtomatisService $absensi) {}

    public function index(Request $request)
    {
        $profile = $request->user()->muridProfile;
        abort_if(! $profile, 422, 'Murid belum terdaftar.');

        $materi = Materi::whereHas('mapel', fn ($q) => $q->where('paket', $profile->paket))
            ->where(fn ($q) => $q->whereNull('kelas_id')->orWhere('kelas_id', $profile->kelas_id))
            ->when($request->mapel_id, fn ($q) => $q->where('mapel_id', $request->mapel_id))
            ->with(['mapel:id,nama_mapel', 'guru:id,nama,avatar_url', 'reaksi'])
            ->withCount('diskusi')
            ->latest()->paginate(15);

        $userId = $request->user()->id;
        $materi->getCollection()->transform(function (Materi $item) use ($userId) {
            $item->setAttribute('reaksi_summary', MateriReaksi::summarize($item->reaksi, $userId));

            return $item;
        });

        // Membuka daftar materi satu mapel = dianggap "hadir" hari itu (kalau
        // belum ada baris absensi sama sekali — lihat AbsensiOtomatisService).
        // Ini melengkapi tipe materi "teks"/"link" yang tidak pernah menyentuh
        // endpoint download/stream di bawah, jadi sebelumnya tidak pernah
        // tercatat hadir sama sekali.
        if ($request->filled('mapel_id')) {
            $this->absensi->tandaiHadir($request->user(), (int) $request->mapel_id, 'Otomatis: membuka materi mapel');
        }

        return response()->json(['data' => $materi]);
    }

    /**
     * Aturan bisnis #14: hanya murid yang terdaftar di mapel & kelas materi
     * tsb yang bisa akses (materi lama tanpa kelas_id tetap dianggap berlaku
     * untuk seluruh paket, demi kompatibilitas data lama).
     */
    private function pastikanBolehAkses(Request $request, Materi $materi): void
    {
        $profile = $request->user()->muridProfile;
        abort_unless($profile && $materi->mapel->paket === $profile->paket, 403, 'Anda tidak terdaftar di mapel ini.');
        abort_unless(
            $materi->kelas_id === null || $materi->kelas_id === $profile->kelas_id,
            403,
            'Materi ini tidak ditujukan untuk kelas Anda.'
        );
    }

    public function download(Request $request, Materi $materi)
    {
        $this->pastikanBolehAkses($request, $materi);

        $this->absensi->tandaiHadir($request->user(), $materi->mapel_id, "Otomatis: unduh materi \"{$materi->judul}\"");

        return Storage::disk('public')->download($materi->file_url);
    }

    /** GET /murid/materi/{materi}/stream — video inline (bukan attachment) untuk diputar di dalam aplikasi. */
    public function stream(Request $request, Materi $materi)
    {
        $this->pastikanBolehAkses($request, $materi);
        abort_unless($materi->tipe === 'video', 422, 'Materi ini bukan video.');

        return Storage::disk('public')->response($materi->file_url);
    }

    /**
     * POST /murid/materi/{materi}/selesai-tonton — dipanggil klien saat video
     * sudah diputar sampai selesai (>=98% durasi). Baru di titik ini progres
     * dicatat sebagai kehadiran, bukan saat baru mulai menonton.
     */
    public function selesaiTonton(Request $request, Materi $materi)
    {
        $this->pastikanBolehAkses($request, $materi);
        abort_unless($materi->tipe === 'video', 422, 'Materi ini bukan video.');

        $this->absensi->tandaiHadir($request->user(), $materi->mapel_id, "Otomatis: menonton video \"{$materi->judul}\" sampai selesai");

        return response()->json(['message' => 'Progres menonton tercatat.']);
    }
}
