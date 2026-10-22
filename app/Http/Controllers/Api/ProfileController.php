<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Profil akun sendiri (semua role: admin/guru/murid) — lihat/ubah nama & ganti password.
 * Data khusus role (mapel guru, kelas murid, dsb.) tetap diubah lewat endpoint admin terkait.
 */
class ProfileController extends Controller
{
    public function update(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'nama' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'bio' => ['sometimes', 'nullable', 'string', 'max:500'],
            'no_hp' => ['sometimes', 'nullable', 'string', 'max:20'],
            'password_baru' => ['sometimes', 'required', 'string', 'min:8', 'confirmed'],
            'password_saat_ini' => ['required_with:password_baru', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid.', 'errors' => $validator->errors()], 422);
        }

        if ($request->filled('password_baru')) {
            if (! Hash::check($request->input('password_saat_ini'), $user->password)) {
                return response()->json(['message' => 'Password saat ini salah.'], 422);
            }
            $user->password = Hash::make($request->input('password_baru'));
        }

        $user->fill($request->only(['nama', 'email', 'bio', 'no_hp']));
        $user->save();

        return response()->json(['data' => $user->fresh()]);
    }

    /** POST /api/profile/avatar — unggah/ganti foto profil (semua role). */
    public function updateAvatar(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'avatar' => ['required', 'image', 'max:2048'], // max 2MB
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid.', 'errors' => $validator->errors()], 422);
        }

        // Hapus foto lama jika sebelumnya disimpan lokal di folder avatars/.
        if ($user->avatar_url) {
            $oldPath = parse_url($user->avatar_url, PHP_URL_PATH) ?: $user->avatar_url;
            $oldPath = ltrim(str_replace('/storage/', '', $oldPath), '/');
            if (str_starts_with($oldPath, 'avatars/') && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        $ext = $request->file('avatar')->getClientOriginalExtension();
        $path = $request->file('avatar')->storeAs('avatars', "{$user->id}.{$ext}", 'public');

        $user->avatar_url = Storage::disk('public')->url($path);
        $user->save();

        return response()->json(['data' => $user->fresh()]);
    }

    /**
     * DELETE /api/profile — hapus akun sendiri (soft delete, User model pakai
     * SoftDeletes). Cabut semua token supaya sesi lain langsung ter-logout juga.
     */
    public function destroy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'password' => ['required', 'string'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid.', 'errors' => $validator->errors()], 422);
        }

        $user = $request->user();

        // Admin sengaja TIDAK boleh hapus akun sendiri lewat sini — kalau dia
        // satu-satunya admin_level 'super', sekolah bisa kehilangan akses kelola
        // akun admin sama sekali (lihat Bagian I/AdminAkunController). Penghapusan
        // akun admin harus lewat admin super LAIN via menu Kelola Admin.
        abort_if($user->role === 'admin', 422, 'Akun admin tidak bisa menghapus diri sendiri — hubungi admin super lain untuk menonaktifkan akun ini lewat menu Kelola Admin.');

        if (! Hash::check($request->input('password'), $user->password)) {
            return response()->json(['message' => 'Password salah.'], 422);
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'Akun berhasil dihapus.']);
    }
}
