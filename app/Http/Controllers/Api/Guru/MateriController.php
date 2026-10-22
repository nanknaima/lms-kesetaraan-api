<?php

namespace App\Http\Controllers\Api\Guru;

use App\Http\Controllers\Api\Guru\Concerns\ResolvesPenugasan;
use App\Http\Controllers\Controller;
use App\Models\Materi;
use App\Models\MateriReaksi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class MateriController extends Controller
{
    use ResolvesPenugasan;

    public function index(Request $request)
    {
        $materi = Materi::where('guru_id', $request->user()->id)
            ->when($request->kelas_id, fn ($q) => $q->where('kelas_id', $request->kelas_id))
            ->with(['kelas:id,nama_kelas', 'mapel:id,nama_mapel', 'reaksi'])
            ->withCount('diskusi')
            ->latest()->paginate(15);

        $userId = $request->user()->id;
        $materi->getCollection()->transform(function (Materi $item) use ($userId) {
            $item->setAttribute('reaksi_summary', MateriReaksi::summarize($item->reaksi, $userId));

            return $item;
        });

        return response()->json(['data' => $materi]);
    }

    public function store(Request $request)
    {
        $guru = $request->user();

        $validator = Validator::make($request->all(), [
            'penugasan_id' => ['sometimes', 'integer', 'exists:penugasan_guru,id'],
            'kelas_id' => ['sometimes', 'integer', 'exists:kelas,id'],
            'judul' => ['required', 'string', 'max:255'],
            'tipe' => ['required', 'in:pdf,video,link,teks,ppt,docx,gambar'],
            'file' => ['required_if:tipe,pdf,video', 'file', 'max:51200'], // 50MB
            'link' => ['required_if:tipe,link', 'url'],
            'deskripsi' => ['nullable', 'string'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid.', 'errors' => $validator->errors()], 422);
        }

        ['mapel_id' => $mapelId, 'kelas_id' => $kelasId] = $this->resolvePenugasan($request);

        $fileUrl = match ($request->tipe) {
            'pdf', 'video' => $request->file('file')->store('materi', 'public'),
            'link' => $request->link,
            default => null,
        };

        $materi = Materi::create([
            'mapel_id' => $mapelId,
            'guru_id' => $guru->id,
            'kelas_id' => $kelasId,
            'judul' => $request->judul,
            'tipe' => $request->tipe,
            'file_url' => $fileUrl,
            'deskripsi' => $request->deskripsi,
        ]);

        return response()->json(['data' => $materi], 201);
    }

    public function show(Request $request, Materi $materi)
    {
        $this->authorizeOwnership($request, $materi);

        return response()->json(['data' => $materi]);
    }

    public function update(Request $request, Materi $materi)
    {
        $this->authorizeOwnership($request, $materi);
        $materi->update($request->only(['judul', 'deskripsi', 'kelas_id']));

        return response()->json(['data' => $materi]);
    }

    public function destroy(Request $request, Materi $materi)
    {
        $this->authorizeOwnership($request, $materi);
        $materi->delete();

        return response()->json(['message' => 'Materi dihapus.']);
    }

    public function download(Request $request, Materi $materi)
    {
        $this->authorizeOwnership($request, $materi);
        abort_if(! $materi->file_url, 404, 'Berkas tidak tersedia.');

        return Storage::disk('public')->download($materi->file_url);
    }

    /** GET /guru/materi/{materi}/stream — pratinjau video milik sendiri (bukan attachment). */
    public function stream(Request $request, Materi $materi)
    {
        $this->authorizeOwnership($request, $materi);
        abort_unless($materi->tipe === 'video', 422, 'Materi ini bukan video.');

        return Storage::disk('public')->response($materi->file_url);
    }

    private function authorizeOwnership(Request $request, Materi $materi): void
    {
        abort_unless($materi->guru_id === $request->user()->id, 403);
    }
}
