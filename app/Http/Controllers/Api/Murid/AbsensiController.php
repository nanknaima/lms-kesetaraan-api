<?php

namespace App\Http\Controllers\Api\Murid;

use App\Http\Controllers\Controller;
use App\Models\Absensi;
use App\Models\Mapel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AbsensiController extends Controller
{
    public function index(Request $request)
    {
        $data = Absensi::where('murid_id', $request->user()->id)
            ->when($request->bulan, fn ($q) => $q->where('tanggal', 'like', "{$request->bulan}%"))
            ->when($request->mapel_id, fn ($q) => $q->where('mapel_id', $request->mapel_id))
            ->with('mapel:id,nama_mapel')
            ->orderByDesc('tanggal')
            ->get();

        return response()->json(['data' => $data]);
    }

    /**
     * POST /murid/absensi — murid ajukan izin/sakit sendiri (BUKAN "hadir"
     * ataupun "alpa", keduanya cuma boleh dari guru/sistem). updateOrCreate
     * per (mapel_id, murid_id, tanggal) — sengaja MENIMPA baris apa pun yang
     * ada di tanggal itu (termasuk "hadir" otomatis), beda dari
     * AbsensiOtomatisService::tandaiHadir() yang justru tidak boleh menimpa.
     */
    public function store(Request $request)
    {
        $murid = $request->user();
        $profile = $murid->muridProfile;
        abort_if(! $profile, 422, 'Murid belum terdaftar.');

        $validator = Validator::make($request->all(), [
            'mapel_id' => ['required', 'integer', 'exists:mapel,id'],
            'tanggal' => ['nullable', 'date', 'before_or_equal:today'],
            'status' => ['required', 'in:izin,sakit'],
            'keterangan' => ['required', 'string', 'max:500'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid.', 'errors' => $validator->errors()], 422);
        }

        $mapelId = (int) $request->mapel_id;
        abort_unless(
            Mapel::where('id', $mapelId)->where('paket', $profile->paket)->exists(),
            403,
            'Anda tidak terdaftar di mapel ini.'
        );

        $tanggal = $request->filled('tanggal') ? $request->tanggal : now()->toDateString();

        $absensi = Absensi::updateOrCreate(
            ['mapel_id' => $mapelId, 'murid_id' => $murid->id, 'tanggal' => $tanggal],
            ['status' => $request->status, 'keterangan' => $request->keterangan, 'otomatis' => false]
        );

        return response()->json(['data' => $absensi], 201);
    }
}
