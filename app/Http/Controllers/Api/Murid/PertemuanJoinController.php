<?php

namespace App\Http\Controllers\Api\Murid;

use App\Http\Controllers\Controller;
use App\Models\PertemuanOnline;
use App\Services\AbsensiOtomatisService;
use Illuminate\Http\Request;

class PertemuanJoinController extends Controller
{
    public function __construct(private AbsensiOtomatisService $absensi) {}

    /**
     * GET /api/pertemuan-online/{pertemuan}/join
     * Aturan bisnis #10: link hanya bisa diakses murid yang terdaftar di kelas/mapel tsb.
     */
    public function __invoke(Request $request, PertemuanOnline $pertemuan)
    {
        $murid = $request->user();

        abort_unless(
            $murid->muridProfile?->kelas_id === $pertemuan->kelas_id,
            403,
            'Anda tidak terdaftar di kelas untuk pertemuan ini.'
        );

        abort_unless(
            $pertemuan->joinAktif(),
            422,
            $pertemuan->status === 'batal'
                ? 'Pertemuan ini dibatalkan oleh guru.'
                : ($pertemuan->sudahSelesai()
                    ? 'Pertemuan ini sudah selesai.'
                    : 'Tombol join baru aktif 10 menit sebelum jadwal dimulai.')
        );

        // Setelah semua validasi lolos, SEBELUM link dikembalikan. Kegagalan absensi
        // ditangani di dalam service (tidak melempar) sehingga join tetap berhasil.
        $this->absensi->tandaiHadirPertemuan($murid, $pertemuan);

        return response()->json(['data' => ['link_meeting' => $pertemuan->link_meeting]]);
    }
}
