<?php

namespace App\Http\Controllers\Api\Guru;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\Mapel;
use App\Models\PenugasanGuru;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Penugasan mandiri guru ke (mapel, kelas) — jawaban atas "10 akun guru
 * ingin mengambil mata pelajaran, algoritmanya harus jelas": klaim eksklusif
 * first-come-first-served, dijaga oleh unique constraint di database supaya
 * tidak ada dua guru berebut kombinasi (mapel, kelas) yang sama walau
 * request-nya datang bersamaan (race condition aman karena constraint-nya
 * di level DB, bukan cuma dicek di PHP).
 */
class PenugasanController extends Controller
{
    /** GET /guru/penugasan — daftar penugasan milik guru login sendiri. */
    public function index(Request $request)
    {
        $penugasan = PenugasanGuru::where('guru_id', $request->user()->id)
            ->with(['mapel:id,nama_mapel,kode,paket', 'kelas:id,nama_kelas,paket,tingkat'])
            ->get();

        return response()->json(['data' => $penugasan]);
    }

    /**
     * GET /guru/penugasan/tersedia?mapel_id= — kombinasi (mapel, kelas) yang
     * BELUM diambil guru manapun. Mapel & Kelas hanya nyambung lewat paket
     * yang sama (tidak ada FK langsung), jadi kombinasi validnya adalah
     * silang mapel x kelas dengan paket sama, dikurangi yang sudah diklaim.
     */
    public function tersedia(Request $request)
    {
        $mapelQuery = Mapel::query();
        if ($request->filled('mapel_id')) {
            $mapelQuery->where('id', $request->integer('mapel_id'));
        }
        $mapelList = $mapelQuery->get(['id', 'nama_mapel', 'kode', 'paket']);

        $semuaPenugasan = PenugasanGuru::all(['mapel_id', 'kelas_id'])
            ->map(fn ($p) => "{$p->mapel_id}-{$p->kelas_id}")
            ->flip();

        $kombinasi = [];
        foreach ($mapelList as $mapel) {
            $kelasList = Kelas::where('paket', $mapel->paket)->get(['id', 'nama_kelas', 'paket', 'tingkat']);
            foreach ($kelasList as $kelas) {
                if (isset($semuaPenugasan["{$mapel->id}-{$kelas->id}"])) {
                    continue;
                }
                $kombinasi[] = [
                    'mapel_id' => $mapel->id,
                    'nama_mapel' => $mapel->nama_mapel,
                    'kode_mapel' => $mapel->kode,
                    'kelas_id' => $kelas->id,
                    'nama_kelas' => $kelas->nama_kelas,
                    'paket' => $kelas->paket,
                    'tingkat' => $kelas->tingkat,
                ];
            }
        }
        unset($sudahDiambil);

        return response()->json(['data' => $kombinasi]);
    }

    /** POST /guru/penugasan {mapel_id, kelas_id} — klaim satu kombinasi. */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mapel_id' => ['required', 'integer', 'exists:mapel,id'],
            'kelas_id' => ['required', 'integer', 'exists:kelas,id'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid.', 'errors' => $validator->errors()], 422);
        }

        $mapel = Mapel::find($request->mapel_id);
        $kelas = Kelas::find($request->kelas_id);
        abort_unless($mapel->paket === $kelas->paket, 422, 'Mapel dan kelas ini beda paket, tidak bisa dipasangkan.');

        try {
            $penugasan = PenugasanGuru::create([
                'guru_id' => $request->user()->id,
                'mapel_id' => $request->mapel_id,
                'kelas_id' => $request->kelas_id,
            ]);
        } catch (QueryException $e) {
            if ((int) $e->getCode() === 23000) {
                return response()->json(['message' => 'Kombinasi mapel & kelas ini sudah diambil guru lain.'], 409);
            }
            throw $e;
        }

        return response()->json(['data' => $penugasan->load(['mapel:id,nama_mapel,kode,paket', 'kelas:id,nama_kelas,paket,tingkat'])], 201);
    }

    /** DELETE /guru/penugasan/{penugasan} — lepas penugasan milik sendiri. */
    public function destroy(Request $request, PenugasanGuru $penugasan)
    {
        abort_unless($penugasan->guru_id === $request->user()->id, 403);
        $penugasan->delete();

        return response()->json(['message' => 'Penugasan dilepas.']);
    }
}
