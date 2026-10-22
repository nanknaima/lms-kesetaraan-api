<?php

namespace App\Http\Controllers\Api\Murid;

use App\Http\Controllers\Controller;
use App\Models\SubmissionTugas;
use App\Models\Tugas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class TugasSubmitController extends Controller
{
    /** GET /api/murid/tugas — tugas untuk kelas murid, plus status pengerjaannya. */
    public function index(Request $request)
    {
        $murid = $request->user();
        $kelasId = $murid->muridProfile?->kelas_id;
        abort_if(! $kelasId, 422, 'Murid belum terdaftar di kelas manapun.');

        $tugas = Tugas::where('kelas_id', $kelasId)
            ->when($request->mapel_id, fn ($q) => $q->where('mapel_id', $request->mapel_id))
            ->with(['submissions' => fn ($q) => $q->where('murid_id', $murid->id)])
            ->latest('deadline')
            ->get()
            ->map(function (Tugas $t) {
                $submission = $t->submissions->first();

                return [
                    'id' => $t->id,
                    'judul' => $t->judul,
                    'deadline' => $t->deadline,
                    'status' => $submission?->status ?? ($t->isLewatDeadline() ? 'terlewat' : 'belum'),
                    'nilai' => $submission?->nilai,
                ];
            });

        return response()->json(['data' => $tugas]);
    }

    /** GET /api/murid/tugas/{tugas} — detail tugas + submission murid (jika sudah pernah mengumpulkan). */
    public function show(Request $request, Tugas $tugas)
    {
        $murid = $request->user();
        abort_unless($murid->muridProfile?->kelas_id === $tugas->kelas_id, 403, 'Tugas ini bukan untuk kelas Anda.');

        $submission = SubmissionTugas::where('tugas_id', $tugas->id)->where('murid_id', $murid->id)->first();

        return response()->json(['data' => [
            'id' => $tugas->id,
            'judul' => $tugas->judul,
            'instruksi' => $tugas->instruksi,
            'lampiran_url' => $tugas->lampiran_url,
            'deadline' => $tugas->deadline,
            'mapel' => $tugas->mapel?->nama_mapel,
            'lewat_deadline' => $tugas->isLewatDeadline(),
            'submission' => $submission ? [
                'jawaban' => $submission->jawaban,
                'file_url' => $submission->file_url,
                'nilai' => $submission->nilai,
                'feedback' => $submission->feedback,
                'status' => $submission->status,
                'submitted_at' => $submission->submitted_at,
            ] : null,
        ]]);
    }

    /** POST /api/murid/tugas/{tugas}/submit — upload jawaban (file dan/atau teks). */
    public function submit(Request $request, Tugas $tugas)
    {
        $murid = $request->user();
        abort_unless($murid->muridProfile?->kelas_id === $tugas->kelas_id, 403, 'Tugas ini bukan untuk kelas Anda.');

        $validator = Validator::make($request->all(), [
            'jawaban' => ['nullable', 'string'],
            'file' => ['nullable', 'file', 'max:10240'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid.', 'errors' => $validator->errors()], 422);
        }
        if (! $request->filled('jawaban') && ! $request->hasFile('file')) {
            return response()->json(['message' => 'Isi jawaban atau lampirkan file.'], 422);
        }

        $fileUrl = $request->hasFile('file')
            ? $request->file('file')->store("tugas/{$tugas->id}/submission", 'public')
            : null;

        $status = $tugas->isLewatDeadline() ? 'terlambat' : 'belum'; // 'belum' = sudah dikirim, menunggu nilai

        $submission = SubmissionTugas::updateOrCreate(
            ['tugas_id' => $tugas->id, 'murid_id' => $murid->id],
            [
                'jawaban' => $request->jawaban,
                'file_url' => $fileUrl ?? SubmissionTugas::where('tugas_id', $tugas->id)
                    ->where('murid_id', $murid->id)->value('file_url'),
                'submitted_at' => now(),
                'status' => $status,
            ]
        );

        return response()->json(['data' => $submission], 201);
    }

    /**
     * DELETE /api/murid/tugas/{tugas}/submission — batalkan pengiriman
     * (hapus baris submission + berkasnya) supaya murid bisa mengirim ulang
     * dari nol. Tidak boleh kalau sudah dinilai guru (aturan bisnis baru).
     */
    public function batalkan(Request $request, Tugas $tugas)
    {
        $murid = $request->user();
        abort_unless($murid->muridProfile?->kelas_id === $tugas->kelas_id, 403, 'Tugas ini bukan untuk kelas Anda.');

        $submission = SubmissionTugas::where('tugas_id', $tugas->id)->where('murid_id', $murid->id)->first();
        abort_if(! $submission, 404, 'Anda belum pernah mengumpulkan tugas ini.');
        abort_if($submission->status === 'dinilai', 422, 'Tugas yang sudah dinilai tidak bisa dibatalkan.');

        if ($submission->file_url) {
            Storage::disk('public')->delete($submission->file_url);
        }
        $submission->delete();

        return response()->json(['message' => 'Pengiriman tugas dibatalkan.']);
    }

    /**
     * GET /api/murid/tugas/{tugas}/submission/download — versi murid dari
     * download submission (beda dari endpoint guru yang scope-nya per guru
     * pengampu): murid hanya boleh unduh berkas miliknya sendiri.
     */
    public function downloadSubmission(Request $request, Tugas $tugas)
    {
        $murid = $request->user();
        abort_unless($murid->muridProfile?->kelas_id === $tugas->kelas_id, 403, 'Tugas ini bukan untuk kelas Anda.');

        $submission = SubmissionTugas::where('tugas_id', $tugas->id)->where('murid_id', $murid->id)->first();
        abort_if(! $submission || ! $submission->file_url, 404, 'Berkas tidak tersedia.');

        return Storage::disk('public')->download($submission->file_url);
    }

    /**
     * GET /api/murid/tugas/{tugas}/lampiran/download — berkas soal/lampiran
     * yang diunggah GURU saat membuat tugas ini (beda dari jawaban murid di
     * atas, yang diunggah murid sendiri).
     */
    public function downloadLampiran(Request $request, Tugas $tugas)
    {
        $murid = $request->user();
        abort_unless($murid->muridProfile?->kelas_id === $tugas->kelas_id, 403, 'Tugas ini bukan untuk kelas Anda.');
        abort_unless($tugas->lampiran_url, 404, 'Tugas ini tidak punya lampiran.');

        return Storage::disk('public')->download($tugas->lampiran_url);
    }
}
