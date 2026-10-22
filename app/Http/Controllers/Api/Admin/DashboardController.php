<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\User;

class DashboardController extends Controller
{
    /** GET /api/admin/dashboard */
    public function __invoke()
    {
        return response()->json(['data' => [
            'total_guru' => User::where('role', 'guru')->where('status', 'aktif')->count(),
            'total_murid' => User::where('role', 'murid')->where('status', 'aktif')->count(),
            'total_kelas' => Kelas::count(),
            'murid_per_paket' => User::where('role', 'murid')->where('status', 'aktif')
                ->join('murid_profile', 'users.id', '=', 'murid_profile.user_id')
                ->selectRaw('murid_profile.paket, count(*) as total')
                ->groupBy('murid_profile.paket')
                ->pluck('total', 'paket'),
            'pendaftaran_pending' => \App\Models\Pendaftaran::where('status', 'pending')->count(),
        ]]);
    }
}
