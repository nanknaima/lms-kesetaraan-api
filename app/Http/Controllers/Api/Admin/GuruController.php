<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendKredensialJob;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/** CRUD akun Guru (aturan bisnis #1: 1 guru = 1 mapel). */
class GuruController extends Controller
{
    public function index(Request $request)
    {
        $guru = User::where('role', 'guru')
            ->with('guruProfile.mapel')
            ->when($request->search, fn ($q) => $q->where('nama', 'like', "%{$request->search}%"))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->paginate($request->integer('per_page', 15));

        return response()->json(['data' => $guru]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'nip' => ['required', 'string', 'unique:users,nip'],
            'mapel_id' => ['required', 'exists:mapel,id'],
            'no_hp' => ['nullable', 'string'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid.', 'errors' => $validator->errors()], 422);
        }

        // 1 guru = 1 mapel: tolak jika mapel sudah dipegang guru aktif lain.
        $sudahAda = User::where('role', 'guru')->where('status', 'aktif')
            ->whereHas('guruProfile', fn ($q) => $q->where('mapel_id', $request->mapel_id))
            ->exists();
        if ($sudahAda) {
            return response()->json(['message' => 'Mapel ini sudah diampu guru lain yang aktif.'], 422);
        }

        $passwordAwal = Str::password(10);

        $guru = DB::transaction(function () use ($request, $passwordAwal) {
            $user = User::create([
                'nama' => $request->nama,
                'email' => $request->email,
                'password' => Hash::make($passwordAwal),
                'role' => 'guru',
                'nip' => $request->nip,
                'status' => 'aktif',
            ]);
            $user->guruProfile()->create([
                'mapel_id' => $request->mapel_id,
                'no_hp' => $request->no_hp,
            ]);

            return $user;
        });

        // Sebelumnya password acak ini tidak pernah dikembalikan/dikirim ke mana
        // pun — guru baru tidak akan pernah bisa login. Disamakan dengan pola
        // Admin\MuridController::store() (kirim email + tampilkan sekali ke admin).
        SendKredensialJob::dispatch($guru, $passwordAwal);

        return response()->json([
            'data' => $guru->load('guruProfile.mapel'),
            'password_awal' => $passwordAwal,
        ], 201);
    }

    public function show(User $guru)
    {
        return response()->json(['data' => $guru->load('guruProfile.mapel')]);
    }

    public function update(Request $request, User $guru)
    {
        $guru->update($request->only(['nama', 'email']));
        if ($request->filled('mapel_id')) {
            $guru->guruProfile()->update(['mapel_id' => $request->mapel_id]);
        }

        return response()->json(['data' => $guru->fresh('guruProfile.mapel')]);
    }

    /** Nonaktifkan (soft delete) — aturan bisnis #6. */
    public function destroy(User $guru)
    {
        $guru->update(['status' => 'nonaktif']);
        $guru->delete();

        return response()->json(['message' => 'Akun guru dinonaktifkan.']);
    }
}
