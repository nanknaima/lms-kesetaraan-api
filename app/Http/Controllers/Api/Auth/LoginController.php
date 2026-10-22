<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class LoginController extends Controller
{
    /**
     * POST /api/auth/login
     * Body: { identifier, password }
     * identifier boleh: email (semua role), NIP (guru), atau NIS (murid) — aturan bisnis #4 & #5.
     */
    public function __invoke(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'identifier' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid.', 'errors' => $validator->errors()], 422);
        }

        $identifier = $request->string('identifier')->trim()->toString();

        $user = User::query()
            ->where('email', $identifier)
            ->orWhere('nis', $identifier)
            ->orWhere('nip', $identifier)
            ->first();

        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            return response()->json(['message' => 'Kredensial tidak valid.'], 401);
        }

        if ($user->status !== 'aktif') {
            return response()->json(['message' => 'Akun Anda tidak aktif. Hubungi admin sekolah.'], 403);
        }

        // Access token umur pendek + refresh token umur panjang (disimpan sbg token terpisah dgn ability 'refresh')
        $accessToken = $user->createToken('access', ['*'], now()->addHours(2))->plainTextToken;
        $refreshToken = $user->createToken('refresh', ['refresh'], now()->addDays(30))->plainTextToken;

        return response()->json([
            'data' => [
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'token_type' => 'Bearer',
                'user' => [
                    'id' => $user->id,
                    'nama' => $user->nama,
                    'email' => $user->email,
                    'role' => $user->role,
                    'nis' => $user->nis,
                    'nip' => $user->nip,
                    'avatar_url' => $user->avatar_url,
                ],
            ],
        ]);
    }
}
