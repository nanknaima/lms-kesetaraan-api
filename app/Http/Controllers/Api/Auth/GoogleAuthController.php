<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

/**
 * Login "user lama" (murid/guru/admin yang akunnya sudah ada) pakai Google.
 *
 * PENTING: Google TIDAK dipakai untuk membuat akun baru — kalau email Google
 * belum cocok dengan akun manapun, user diarahkan balik ke halaman login
 * dengan pesan untuk mendaftar lewat menu Pendaftaran (murid) atau
 * menghubungi admin (guru), sesuai aturan bisnis NIS/NIP yang sudah ada.
 */
class GoogleAuthController extends Controller
{
    /** GET /api/auth/google/redirect — browser dialihkan ke halaman login Google. */
    public function redirect()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    /** GET /api/auth/google/callback — Google mengarahkan balik ke sini setelah user login. */
    public function callback(Request $request)
    {
        $frontend = rtrim(config('app.frontend_url'), '/');

        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (\Throwable $e) {
            return redirect("{$frontend}/login?google_error=".urlencode('Login Google gagal atau dibatalkan.'));
        }

        $user = User::where('google_id', $googleUser->getId())
            ->orWhere('email', $googleUser->getEmail())
            ->first();

        if (! $user) {
            return redirect("{$frontend}/login?google_error=".urlencode(
                'Email Google ini belum terdaftar di sekolah. Daftar dulu lewat menu Pendaftaran, atau hubungi admin jika Anda guru/staf.'
            ));
        }

        if ($user->status !== 'aktif') {
            return redirect("{$frontend}/login?google_error=".urlencode('Akun Anda tidak aktif. Hubungi admin sekolah.'));
        }

        if (! $user->google_id) {
            $user->forceFill(['google_id' => $googleUser->getId()])->save();
        }

        $accessToken = $user->createToken('access', ['*'], now()->addHours(2))->plainTextToken;
        $refreshToken = $user->createToken('refresh', ['refresh'], now()->addDays(30))->plainTextToken;

        return redirect("{$frontend}/auth/google/callback?".http_build_query([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
        ]));
    }
}
