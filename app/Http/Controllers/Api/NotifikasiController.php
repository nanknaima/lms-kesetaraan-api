<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notifikasi;
use Illuminate\Http\Request;

class NotifikasiController extends Controller
{
    public function index(Request $request)
    {
        $data = Notifikasi::where('user_id', $request->user()->id)
            ->orderBy('is_read')->orderByDesc('created_at')
            ->paginate(20);

        return response()->json(['data' => $data]);
    }

    public function markRead(Request $request, Notifikasi $notifikasi)
    {
        abort_unless($notifikasi->user_id === $request->user()->id, 403);
        $notifikasi->update(['is_read' => true]);

        return response()->json(['data' => $notifikasi]);
    }
}
