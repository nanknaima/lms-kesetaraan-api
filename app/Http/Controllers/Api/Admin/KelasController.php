<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\Kuis;
use App\Models\Materi;
use App\Models\MuridProfile;
use App\Models\PenugasanGuru;
use App\Models\PenugasanMengajar;
use App\Models\PertemuanOnline;
use App\Models\Tugas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class KelasController extends Controller
{
    public function index(Request $request)
    {
        $kelas = Kelas::with('waliKelas:id,nama')
            ->when($request->paket, fn ($q) => $q->where('paket', $request->paket))
            ->paginate($request->integer('per_page', 20));

        return response()->json(['data' => $kelas]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama_kelas' => ['required', 'string', 'max:255'],
            'paket' => ['required', 'in:A,B,C'],
            'tingkat' => ['required', 'string'],
            'wali_kelas_id' => ['nullable', 'exists:users,id'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid.', 'errors' => $validator->errors()], 422);
        }

        return response()->json(['data' => Kelas::create($validator->validated())], 201);
    }

    public function show(Kelas $kelas)
    {
        return response()->json(['data' => $kelas->load('waliKelas', 'murid.user')]);
    }

    public function update(Request $request, Kelas $kelas)
    {
        $kelas->update($request->only(['nama_kelas', 'tingkat', 'wali_kelas_id']));

        return response()->json(['data' => $kelas]);
    }

    public function destroy(Kelas $kelas)
    {
        // Sama seperti Admin\MapelController::destroy() — cek semua tabel
        // yang punya kelas_id sebelum hapus, karena beberapa cascadeOnDelete
        // (tugas, kuis, pertemuan_online, penugasan). penugasan_guru (interim,
        // mobile) ikut dicek karena masih dual-support.
        $dipakai = PenugasanMengajar::where('kelas_id', $kelas->id)->whereIn('status', ['diajukan', 'disetujui'])->exists()
            || PenugasanGuru::where('kelas_id', $kelas->id)->exists()
            || MuridProfile::where('kelas_id', $kelas->id)->exists()
            || Materi::where('kelas_id', $kelas->id)->exists()
            || Tugas::where('kelas_id', $kelas->id)->exists()
            || Kuis::where('kelas_id', $kelas->id)->exists()
            || PertemuanOnline::where('kelas_id', $kelas->id)->exists();

        abort_if(
            $dipakai,
            422,
            'Kelas ini masih memiliki data terkait (murid/penugasan guru/materi/tugas/kuis/pertemuan online). Tidak bisa dihapus selama masih dipakai.'
        );

        $kelas->delete();

        return response()->json(['message' => 'Kelas dihapus.']);
    }
}
