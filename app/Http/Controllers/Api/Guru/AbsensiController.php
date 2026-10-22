<?php

namespace App\Http\Controllers\Api\Guru;

use App\Http\Controllers\Api\Guru\Concerns\ResolvesPenugasan;
use App\Http\Controllers\Controller;
use App\Models\Absensi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AbsensiController extends Controller
{
    use ResolvesPenugasan;

    public function index(Request $request)
    {
        $mapelId = $this->resolveMapelId($request);

        $data = Absensi::where('mapel_id', $mapelId)
            ->when($request->tanggal, fn ($q) => $q->where('tanggal', $request->tanggal))
            ->with('murid:id,nama,nis')
            ->get();

        return response()->json(['data' => $data]);
    }

    /** Input absensi massal: [{murid_id, status, keterangan}] untuk satu tanggal. */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mapel_id' => ['sometimes', 'integer', 'exists:mapel,id'],
            'tanggal' => ['required', 'date'],
            'data' => ['required', 'array', 'min:1'],
            'data.*.murid_id' => ['required', 'exists:users,id'],
            'data.*.status' => ['required', 'in:hadir,izin,sakit,alpa'],
            'data.*.keterangan' => ['nullable', 'string'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid.', 'errors' => $validator->errors()], 422);
        }

        $mapelId = $this->resolveMapelId($request);

        foreach ($request->data as $row) {
            Absensi::updateOrCreate(
                ['mapel_id' => $mapelId, 'murid_id' => $row['murid_id'], 'tanggal' => $request->tanggal],
                ['status' => $row['status'], 'keterangan' => $row['keterangan'] ?? null, 'otomatis' => false]
            );
        }

        return response()->json(['message' => 'Absensi tersimpan.']);
    }

    public function rekap(Request $request)
    {
        $mapelId = $this->resolveMapelId($request);
        $bulan = $request->query('bulan', now()->format('Y-m'));

        $rekap = Absensi::where('mapel_id', $mapelId)
            ->where('tanggal', 'like', "{$bulan}%")
            ->selectRaw('murid_id, status, count(*) as total')
            ->groupBy('murid_id', 'status')
            ->with('murid:id,nama,nis')
            ->get()
            ->groupBy('murid_id');

        return response()->json(['data' => $rekap]);
    }

    /**
     * GET /guru/absensi/riwayat?mapel_id=&bulan=&search= — daftar baris
     * absensi (termasuk yang tercatat otomatis lewat aktivitas murid) untuk
     * ditampilkan sebagai tabel + pencarian di mobile, sumber yang sama
     * dipakai tombol Export.
     */
    public function riwayat(Request $request)
    {
        $mapelId = $this->resolveMapelId($request);
        $bulan = $request->query('bulan', now()->format('Y-m'));
        $search = $request->query('search');

        $data = Absensi::where('mapel_id', $mapelId)
            ->where('tanggal', 'like', "{$bulan}%")
            ->with('murid:id,nama,nis')
            ->when($search, fn ($q) => $q->whereHas('murid', fn ($q2) => $q2->where('nama', 'like', "%{$search}%")->orWhere('nis', 'like', "%{$search}%")
            ))
            ->orderByDesc('tanggal')
            ->get();

        return response()->json(['data' => $data]);
    }
}
