<?php

namespace App\Http\Controllers\Api\Guru;

use App\Http\Controllers\Api\Guru\Concerns\ResolvesPenugasan;
use App\Http\Controllers\Controller;
use App\Models\Notifikasi;
use App\Models\SubmissionTugas;
use App\Models\Tugas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * Fitur contoh end-to-end: kelola tugas (create → submit → grade → export).
 * Lihat juga: Murid\TugasSubmitController (submit) dan ExportController (export).
 */
class TugasController extends Controller
{
    use ResolvesPenugasan;

    /** GET /api/guru/tugas — list tugas milik guru login. */
    public function index(Request $request)
    {
        $tugas = Tugas::query()
            ->where('guru_id', $request->user()->id)
            ->when($request->kelas_id, fn ($q) => $q->where('kelas_id', $request->kelas_id))
            ->with('mapel:id,nama_mapel')
            ->withCount(['submissions as submitted_count' => fn ($q) => $q->whereNotNull('submitted_at')])
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json(['data' => $tugas]);
    }

    /** POST /api/guru/tugas — buat tugas baru untuk penugasan (mapel+kelas) yang dipilih. */
    public function store(Request $request)
    {
        $guru = $request->user();

        $validator = Validator::make($request->all(), [
            'penugasan_id' => ['sometimes', 'integer', 'exists:penugasan_guru,id'],
            'kelas_id' => ['sometimes', 'integer', 'exists:kelas,id'],
            'judul' => ['required', 'string', 'max:255'],
            'instruksi' => ['nullable', 'string'],
            'deadline' => ['required', 'date', 'after:now'],
            'lampiran' => ['nullable', 'file', 'max:10240'], // 10MB
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid.', 'errors' => $validator->errors()], 422);
        }

        ['mapel_id' => $mapelId, 'kelas_id' => $kelasId] = $this->resolvePenugasan($request);

        $lampiranUrl = null;
        if ($request->hasFile('lampiran')) {
            $lampiranUrl = $request->file('lampiran')->store('tugas/lampiran', 'public');
        }

        $tugas = Tugas::create([
            'mapel_id' => $mapelId,
            'guru_id' => $guru->id,
            'kelas_id' => $kelasId,
            'judul' => $request->judul,
            'instruksi' => $request->instruksi,
            'lampiran_url' => $lampiranUrl,
            'deadline' => $request->deadline,
        ]);

        Notifikasi::kirimKeKelas(
            $kelasId,
            'tugas',
            "Tugas baru: {$tugas->judul}",
            'Deadline: '.\Carbon\Carbon::parse($request->deadline)->format('d/m/Y H:i'),
            "/murid/tugas/{$tugas->id}"
        );

        return response()->json(['data' => $tugas], 201);
    }

    /** GET /api/guru/tugas/{tugas} */
    public function show(Request $request, Tugas $tugas)
    {
        $this->authorizeOwnership($request, $tugas);

        return response()->json(['data' => $tugas->load('kelas', 'mapel')]);
    }

    /** PUT /api/guru/tugas/{tugas} */
    public function update(Request $request, Tugas $tugas)
    {
        $this->authorizeOwnership($request, $tugas);

        $validator = Validator::make($request->all(), [
            'judul' => ['sometimes', 'string', 'max:255'],
            'instruksi' => ['nullable', 'string'],
            'deadline' => ['sometimes', 'date'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid.', 'errors' => $validator->errors()], 422);
        }

        $tugas->update($validator->validated());

        return response()->json(['data' => $tugas]);
    }

    /** DELETE /api/guru/tugas/{tugas} */
    public function destroy(Request $request, Tugas $tugas)
    {
        $this->authorizeOwnership($request, $tugas);
        $tugas->delete();

        return response()->json(['message' => 'Tugas dihapus.']);
    }

    /** GET /api/guru/tugas/{tugas}/submissions — lihat hasil upload murid. */
    public function submissions(Request $request, Tugas $tugas)
    {
        $this->authorizeOwnership($request, $tugas);

        $submissions = $tugas->submissions()
            ->with('murid:id,nama,nis')
            ->orderByRaw("submitted_at IS NULL, submitted_at asc")
            ->get();

        return response()->json(['data' => $submissions]);
    }

    /** GET /api/guru/tugas/{tugas}/submissions/{submission}/download */
    public function downloadSubmission(Request $request, Tugas $tugas, SubmissionTugas $submission)
    {
        $this->authorizeOwnership($request, $tugas);
        abort_unless($submission->tugas_id === $tugas->id, 404);
        abort_unless($submission->file_url, 404, 'Murid belum upload file.');

        // Storage privat: hanya guru & admin yang bisa download (aturan bisnis #8).
        return Storage::disk('public')->download($submission->file_url);
    }

    /** GET /api/guru/tugas/{tugas}/lampiran/download — berkas soal/lampiran tugas milik guru sendiri (beda dari jawaban murid di atas). */
    public function downloadLampiran(Request $request, Tugas $tugas)
    {
        $this->authorizeOwnership($request, $tugas);
        abort_unless($tugas->lampiran_url, 404, 'Tugas ini tidak punya lampiran.');

        return Storage::disk('public')->download($tugas->lampiran_url);
    }

    /** POST /api/guru/submissions/{submission}/grade — input nilai + feedback. */
    public function grade(Request $request, SubmissionTugas $submission)
    {
        $tugas = $submission->tugas;
        $this->authorizeOwnership($request, $tugas);

        $validator = Validator::make($request->all(), [
            'nilai' => ['required', 'numeric', 'min:0', 'max:100'],
            'feedback' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid.', 'errors' => $validator->errors()], 422);
        }

        $submission->update([
            'nilai' => $request->nilai,
            'feedback' => $request->feedback,
            'status' => 'dinilai',
        ]);

        // TODO: dispatch NotifikasiJob ke murid (tipe=nilai)

        return response()->json(['data' => $submission]);
    }

    private function authorizeOwnership(Request $request, Tugas $tugas): void
    {
        abort_unless(
            $request->user()->isAdmin() || $tugas->guru_id === $request->user()->id,
            403,
            'Anda tidak berhak mengelola tugas ini.'
        );
    }
}
