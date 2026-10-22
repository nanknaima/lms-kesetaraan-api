<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class RefreshTokenController extends Controller
{
    /**
     * POST /api/auth/refresh
     * Dipanggil dengan Authorization: Bearer <refresh_token>.
     * Middleware 'auth:sanctum' + ability:refresh (lihat routes/api.php).
     */
    public function __invoke(Request $request)
    {
        $user = $request->user();

        // Cabut refresh token lama, terbitkan pasangan token baru (rotasi token).
        $request->user()->currentAccessToken()->delete();

        $accessToken = $user->createToken('access', ['*'], now()->addHours(2))->plainTextToken;
        $refreshToken = $user->createToken('refresh', ['refresh'], now()->addDays(30))->plainTextToken;

        return response()->json([
            'data' => [
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'token_type' => 'Bearer',
            ],
        ]);
    }
}
