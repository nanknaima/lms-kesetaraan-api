<?php

namespace App\Http\Controllers\Api\Guru;

use App\Http\Controllers\Api\Guru\Concerns\ResolvesPenugasan;
use App\Http\Controllers\Controller;
use App\Models\JawabanKuis;
use App\Models\Kuis;
use App\Models\Notifikasi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class KuisController extends Controller
{
    use ResolvesPenugasan;

    public function index(Request $request)
    {
        return response()->json([
            'data' => Kuis::where('guru_id', $request->user()->id)
                ->with('mapel:id,nama_mapel')
                ->withCount('soal')->latest()->paginate(15),
        ]);
    }

    public function store(Request $request)
    {
        $guru = $request->user();

        $validator = Validator::make($request->all(), [
            'penugasan_id' => ['sometimes', 'integer', 'exists:penugasan_guru,id'],
            'kelas_id' => ['sometimes', 'integer', 'exists:kelas,id'],
            'judul' => ['required', 'string', 'max:255'],
            'durasi_menit' => ['required', 'integer', 'min:1'],
            'random_soal' => ['boolean'],
            'mulai' => ['required', 'date'],
            'selesai' => ['required', 'date', 'after:mulai'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid.', 'errors' => $validator->errors()], 422);
        }

        ['mapel_id' => $mapelId, 'kelas_id' => $kelasId] = $this->resolvePenugasan($request);

        $kuis = Kuis::create([
            'mapel_id' => $mapelId,
            'guru_id' => $guru->id,
            'kelas_id' => $kelasId,
            'judul' => $request->judul,
            'durasi_menit' => $request->durasi_menit,
            'random_soal' => $request->boolean('random_soal'),
            'mulai' => $request->mulai,
            'selesai' => $request->selesai,
        ]);

        Notifikasi::kirimKeKelas(
            $kelasId,
            'kuis',
            "Kuis baru: {$kuis->judul}",
            'Dibuka '.\Carbon\Carbon::parse($request->mulai)->format('d/m/Y H:i').', berakhir '.\Carbon\Carbon::parse($request->selesai)->format('d/m/Y H:i'),
            '/murid/kuis'
        );

        return response()->json(['data' => $kuis], 201);
    }

    public function show(Request $request, Kuis $kuis)
    {
        $this->authorizeOwnership($request, $kuis);

        return response()->json(['data' => $kuis->load('soal')]);
    }

    public function update(Request $request, Kuis $kuis)
    {
        $this->authorizeOwnership($request, $kuis);
        $kuis->update($request->only(['judul', 'durasi_menit', 'random_soal', 'mulai', 'selesai']));

        return response()->json(['data' => $kuis]);
    }

    public function destroy(Request $request, Kuis $kuis)
    {
        $this->authorizeOwnership($request, $kuis);
        $kuis->delete();

        return response()->json(['message' => 'Kuis dihapus.']);
    }

    /** Rekap nilai + daftar jawaban esai yang menunggu penilaian manual. */
    public function hasil(Request $request, Kuis $kuis)
    {
        $this->authorizeOwnership($request, $kuis);

        $esaiPending = JawabanKuis::where('kuis_id', $kuis->id)
            ->whereHas('soal', fn ($q) => $q->where('tipe', 'esai'))
            ->whereNull('skor')
            ->with('murid:id,nama,nis', 'soal:id,pertanyaan')
            ->get();

        $rekapNilai = JawabanKuis::where('kuis_id', $kuis->id)
            ->selectRaw('murid_id, sum(skor) as total_skor')
            ->groupBy('murid_id')->with('murid:id,nama,nis')->get();

        return response()->json(['data' => [
            'rekap_nilai' => $rekapNilai,
            'esai_pending' => $esaiPending,
        ]]);
    }

    public function gradeEsai(Request $request, JawabanKuis $jawaban)
    {
        $validator = Validator::make($request->all(), ['skor' => ['required', 'numeric', 'min:0']]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid.', 'errors' => $validator->errors()], 422);
        }

        $jawaban->update(['skor' => $request->skor, 'auto_graded' => false]);

        return response()->json(['data' => $jawaban]);
    }

    private function authorizeOwnership(Request $request, Kuis $kuis): void
    {
        abort_unless($kuis->guru_id === $request->user()->id, 403);
    }
}
