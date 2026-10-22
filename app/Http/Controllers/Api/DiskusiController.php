<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Diskusi;
use App\Models\DiskusiReaksi;
use App\Models\Materi;
use App\Models\MateriReaksi;
use App\Services\AbsensiOtomatisService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Forum diskusi per mapel ATAU per materi (komentar + reaksi emoji) —
 * dipakai oleh semua role yang login (murid, guru, admin). Diskusi per
 * materi (materi_id terisi) menempel di bawah item materi tertentu; diskusi
 * lama per mapel (materi_id NULL) tetap didukung untuk kompatibilitas mundur.
 */
class DiskusiController extends Controller
{
    public function __construct(private AbsensiOtomatisService $absensi) {}

    /** GET /api/diskusi?mapel_id=&materi_id= — minimal salah satu wajib diisi. */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mapel_id' => ['required_without:materi_id', 'integer', 'exists:mapel,id'],
            'materi_id' => ['required_without:mapel_id', 'integer', 'exists:materi,id'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid.', 'errors' => $validator->errors()], 422);
        }

        $diskusi = Diskusi::when($request->filled('materi_id'), fn ($q) => $q->where('materi_id', $request->integer('materi_id')))
            ->when(! $request->filled('materi_id') && $request->filled('mapel_id'), fn ($q) => $q->where('mapel_id', $request->integer('mapel_id')))
            ->with(['user:id,nama,avatar_url,role', 'reaksi'])
            ->oldest()
            ->paginate($request->integer('per_page', 50));

        $diskusi->getCollection()->transform(function (Diskusi $item) use ($request) {
            $item->setAttribute('reaksi_summary', $this->summarizeReaksi($item, $request->user()->id));

            return $item;
        });

        return response()->json(['data' => $diskusi]);
    }

    /** POST /api/diskusi {mapel_id?, materi_id?, pesan} — minimal salah satu dari mapel_id/materi_id wajib diisi. */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mapel_id' => ['required_without:materi_id', 'integer', 'exists:mapel,id'],
            'materi_id' => ['required_without:mapel_id', 'integer', 'exists:materi,id'],
            'pesan' => ['required', 'string', 'max:2000'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid.', 'errors' => $validator->errors()], 422);
        }

        $materiId = $request->integer('materi_id') ?: null;
        $mapelId = $request->integer('mapel_id') ?: null;

        // Kalau materi_id dikirim tapi mapel_id tidak, isi otomatis dari
        // materi->mapel_id supaya kode lama yang masih baca mapel_id (mis.
        // tandai hadir di bawah) tetap konsisten.
        if ($materiId && ! $mapelId) {
            $mapelId = Materi::findOrFail($materiId)->mapel_id;
        }

        $diskusi = Diskusi::create([
            'mapel_id' => $mapelId,
            'materi_id' => $materiId,
            'user_id' => $request->user()->id,
            'pesan' => $request->pesan,
        ]);

        if ($request->user()->isMurid() && $mapelId) {
            $this->absensi->tandaiHadir($request->user(), $mapelId, 'Otomatis: berkomentar di diskusi');
        }

        $diskusi->load('user:id,nama,avatar_url,role');
        // index() selalu menyertakan reaksi_summary (lihat summarizeReaksi()) —
        // store() harus konsisten menyertakannya juga (array kosong, karena
        // komentar baru belum punya reaksi), supaya frontend yang langsung
        // merender item hasil POST ini (tanpa fetch ulang) tidak crash saat
        // mengakses reaksi_summary.
        $diskusi->setAttribute('reaksi_summary', []);

        return response()->json(['data' => $diskusi], 201);
    }

    /** DELETE /api/diskusi/{diskusi} */
    public function destroy(Request $request, Diskusi $diskusi)
    {
        abort_unless(
            $diskusi->user_id === $request->user()->id || $request->user()->role === 'admin',
            403,
            'Anda tidak berhak menghapus komentar ini.'
        );

        $diskusi->delete();

        return response()->json(['message' => 'Komentar dihapus.']);
    }

    /** POST /api/diskusi/{diskusi}/reaksi */
    public function toggleReaksi(Request $request, Diskusi $diskusi)
    {
        $validator = Validator::make($request->all(), [
            'emoji' => ['required', 'string', 'max:16'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid.', 'errors' => $validator->errors()], 422);
        }

        $userId = $request->user()->id;
        $existing = DiskusiReaksi::where('diskusi_id', $diskusi->id)->where('user_id', $userId)->first();

        if ($existing && $existing->emoji === $request->emoji) {
            $existing->delete();
        } elseif ($existing) {
            $existing->update(['emoji' => $request->emoji]);
        } else {
            DiskusiReaksi::create([
                'diskusi_id' => $diskusi->id,
                'user_id' => $userId,
                'emoji' => $request->emoji,
            ]);
        }

        $diskusi->load('reaksi');

        return response()->json(['data' => $this->summarizeReaksi($diskusi, $userId)]);
    }

    /** Kelompokkan reaksi per emoji: [['emoji' => '👍', 'count' => 3, 'reacted' => true], ...] */
    private function summarizeReaksi(Diskusi $diskusi, int $userId): array
    {
        return MateriReaksi::summarize($diskusi->reaksi, $userId);
    }

    /**
     * POST /api/materi/{materi}/reaksi — reaksi emoji pada POST materi itu
     * sendiri (beda dari reaksi per komentar di atas). Dipakai di feed
     * Diskusi murid & guru supaya bisa "menyukai" materi yang diunggah guru,
     * bukan cuma membalas komentarnya.
     */
    public function toggleReaksiMateri(Request $request, Materi $materi)
    {
        $validator = Validator::make($request->all(), [
            'emoji' => ['required', 'string', 'max:16'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid.', 'errors' => $validator->errors()], 422);
        }

        $userId = $request->user()->id;
        $existing = MateriReaksi::where('materi_id', $materi->id)->where('user_id', $userId)->first();

        if ($existing && $existing->emoji === $request->emoji) {
            $existing->delete();
        } elseif ($existing) {
            $existing->update(['emoji' => $request->emoji]);
        } else {
            MateriReaksi::create([
                'materi_id' => $materi->id,
                'user_id' => $userId,
                'emoji' => $request->emoji,
            ]);
        }

        $materi->load('reaksi');

        return response()->json(['data' => MateriReaksi::summarize($materi->reaksi, $userId)]);
    }
}
