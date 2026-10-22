<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Absensi;
use App\Models\GuruProfile;
use App\Models\Kuis;
use App\Models\Mapel;
use App\Models\Materi;
use App\Models\NilaiAkhir;
use App\Models\PenugasanGuru;
use App\Models\PenugasanMengajar;
use App\Models\PertemuanOnline;
use App\Models\Tugas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MapelController extends Controller
{
    public function index(Request $request)
    {
        $mapel = Mapel::when($request->paket, fn ($q) => $q->where('paket', $request->paket))
            ->paginate($request->integer('per_page', 20));

        return response()->json(['data' => $mapel]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama_mapel' => ['required', 'string', 'max:255'],
            'kode' => ['required', 'string', 'unique:mapel,kode'],
            'paket' => ['required', 'in:A,B,C'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid.', 'errors' => $validator->errors()], 422);
        }

        return response()->json(['data' => Mapel::create($validator->validated())], 201);
    }

    public function show(Mapel $mapel)
    {
        return response()->json(['data' => $mapel]);
    }

    public function update(Request $request, Mapel $mapel)
    {
        $mapel->update($request->only(['nama_mapel', 'kode']));

        return response()->json(['data' => $mapel]);
    }

    public function destroy(Mapel $mapel)
    {
        // Cek semua tabel yang punya mapel_id (lihat migration) sebelum hapus
        // — banyak di antaranya cascadeOnDelete, jadi menghapus mapel yang
        // masih dipakai bisa diam-diam menghapus materi/tugas/kuis/nilai
        // murid yang sudah ada. penugasan_guru (interim, mobile) & guru_profile
        // (skema lama) ikut dicek karena masih dual-support — lihat catatan
        // Tahap 5 Penugasan Mengajar.
        $dipakai = PenugasanMengajar::where('mapel_id', $mapel->id)->whereIn('status', ['diajukan', 'disetujui'])->exists()
            || PenugasanGuru::where('mapel_id', $mapel->id)->exists()
            || GuruProfile::where('mapel_id', $mapel->id)->exists()
            || Materi::where('mapel_id', $mapel->id)->exists()
            || Tugas::where('mapel_id', $mapel->id)->exists()
            || Kuis::where('mapel_id', $mapel->id)->exists()
            || Absensi::where('mapel_id', $mapel->id)->exists()
            || NilaiAkhir::where('mapel_id', $mapel->id)->exists()
            || PertemuanOnline::where('mapel_id', $mapel->id)->exists();

        abort_if(
            $dipakai,
            422,
            'Mata pelajaran ini masih memiliki data terkait (penugasan guru/materi/tugas/nilai). Tidak bisa dihapus selama masih dipakai.'
        );

        $mapel->delete();

        return response()->json(['message' => 'Mapel dihapus.']);
    }
}
