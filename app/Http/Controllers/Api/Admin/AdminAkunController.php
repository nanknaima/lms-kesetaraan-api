<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendKredensialJob;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Kelola akun admin (Bagian 10 — dua tingkat: 'super' vs 'staff').
 * SENGAJA tidak cukup mengandalkan middleware 'role:admin' di routes/api.php
 * (itu cuma memastikan pengguna BERPERAN admin) — buat/hapus akun admin
 * lain harus dicek eksplisit admin_level='super' di sini, supaya admin
 * 'staff' tetap ditolak 403 walau berhasil lolos middleware role.
 */
class AdminAkunController extends Controller
{
    public function index(Request $request)
    {
        $admin = User::where('role', 'admin')
            ->when($request->search, fn ($q) => $q->where('nama', 'like', "%{$request->search}%"))
            ->orderBy('id')
            ->paginate($request->integer('per_page', 15));

        return response()->json(['data' => $admin]);
    }

    public function store(Request $request)
    {
        $this->pastikanSuper($request);

        $validator = Validator::make($request->all(), [
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'admin_level' => ['required', 'in:super,staff'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid.', 'errors' => $validator->errors()], 422);
        }

        $passwordAwal = Str::password(10);

        $admin = User::create([
            'nama' => $request->nama,
            'email' => $request->email,
            'password' => Hash::make($passwordAwal),
            'role' => 'admin',
            'admin_level' => $request->admin_level,
            'status' => 'aktif',
        ]);

        SendKredensialJob::dispatch($admin, $passwordAwal);

        return response()->json(['data' => $admin, 'password_awal' => $passwordAwal], 201);
    }

    /** DELETE /admin/admin-akun/{akun} */
    public function destroy(Request $request, User $akun)
    {
        $this->pastikanSuper($request);
        abort_unless($akun->role === 'admin', 404);
        abort_if($akun->id === $request->user()->id, 422, 'Tidak bisa menghapus akun sendiri yang sedang login.');

        $akun->update(['status' => 'nonaktif']);
        $akun->delete();

        return response()->json(['message' => 'Akun admin dihapus.']);
    }

    private function pastikanSuper(Request $request): void
    {
        abort_unless($request->user()->isSuperAdmin(), 403, 'Hanya admin super yang boleh mengelola akun admin.');
    }
}
