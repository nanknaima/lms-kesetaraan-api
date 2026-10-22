<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Aturan bisnis #1: guru HANYA boleh mengelola mapel yang diampunya.
 * Dipakai pada route yang membawa {mapel} atau resource dengan mapel_id
 * (Tugas, Materi, Kuis, Absensi, PertemuanOnline).
 *
 * Cara pakai di controller (setelah route model binding):
 *   abort_unless($request->user()->mapelDiampu()?->id === $tugas->mapel_id, 403);
 * Middleware ini menjaga level route untuk endpoint index/store yang
 * mapel_id-nya datang dari request body/query, bukan dari model binding.
 */
class EnsureGuruOwnsMapel
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $mapelId = $request->input('mapel_id') ?? $request->route('mapel');

        if ($user->isGuru() && $mapelId && (int) $user->mapelDiampu()?->id !== (int) $mapelId) {
            return response()->json([
                'message' => 'Guru hanya dapat mengakses mapel yang diampu.',
            ], 403);
        }

        return $next($request);
    }
}
