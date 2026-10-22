<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Materi;
use App\Models\SubmissionTugas;
use App\Models\Tugas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Media Library — daftar file yang tersimpan di storage Laravel milik
 * pengguna login, plus total ukuran terpakai (byte). Sumber file beda per
 * role: murid melihat file yang ia unggah saat mengumpulkan tugas; guru
 * melihat file materi & lampiran tugas yang ia unggah sendiri. Belum ada
 * integrasi Google Drive/Firebase sungguhan di backend (lihat UI mobile).
 */
class MediaLibraryController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $items = $user->isGuru() ? $this->mediaGuru($user) : $this->mediaMurid($user);

        return response()->json([
            'data' => $items,
            'used_bytes' => $items->sum('size_bytes'),
        ]);
    }

    private function mediaMurid($murid)
    {
        return SubmissionTugas::where('murid_id', $murid->id)
            ->whereNotNull('file_url')
            ->with('tugas.mapel')
            ->latest('submitted_at')
            ->get()
            ->map(function (SubmissionTugas $s) {
                $ada = $s->file_url && Storage::disk('public')->exists($s->file_url);

                return [
                    'id' => $s->id,
                    'judul' => $s->tugas?->judul,
                    'mapel' => $s->tugas?->mapel?->nama_mapel,
                    'file_url' => $s->file_url,
                    'file_name' => basename($s->file_url),
                    'size_bytes' => $ada ? Storage::disk('public')->size($s->file_url) : 0,
                    'waktu' => $s->submitted_at,
                ];
            })
            ->values();
    }

    private function mediaGuru($guru)
    {
        $materi = Materi::where('guru_id', $guru->id)
            ->whereIn('tipe', ['pdf', 'video', 'ppt', 'docx', 'gambar'])
            ->whereNotNull('file_url')
            ->latest()
            ->get()
            ->map(fn (Materi $m) => $this->baris($m->file_url, $m->judul, null, $m->created_at));

        $tugas = Tugas::where('guru_id', $guru->id)
            ->whereNotNull('lampiran_url')
            ->latest()
            ->get()
            ->map(fn (Tugas $t) => $this->baris($t->lampiran_url, $t->judul, null, $t->created_at));

        return $materi->concat($tugas)->sortByDesc('waktu')->values();
    }

    private function baris(string $path, string $judul, ?string $mapel, $waktu): array
    {
        $ada = Storage::disk('public')->exists($path);

        return [
            'judul' => $judul,
            'mapel' => $mapel,
            'file_url' => $path,
            'file_name' => basename($path),
            'size_bytes' => $ada ? Storage::disk('public')->size($path) : 0,
            'waktu' => $waktu,
        ];
    }
}
