<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware RBAC dasar.
 * Daftar di bootstrap/app.php sebagai alias 'role', dipakai di routes:
 *   Route::middleware('role:admin')->group(...)
 *   Route::middleware('role:guru,admin')->group(...)   // salah satu dari beberapa role
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || $user->status !== 'aktif') {
            return response()->json(['message' => 'Akun tidak aktif atau belum login.'], 403);
        }

        if (! in_array($user->role, $roles, true)) {
            return response()->json(['message' => 'Anda tidak memiliki akses ke resource ini.'], 403);
        }

        return $next($request);
    }
}
