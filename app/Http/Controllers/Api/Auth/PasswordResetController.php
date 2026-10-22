<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Mail\OtpResetPasswordMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

/** Lupa password: kirim OTP 6 digit ke email → verifikasi → set password baru. */
class PasswordResetController extends Controller
{
    /** POST /api/auth/forgot-password — Body: { email } */
    public function sendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid.', 'errors' => $validator->errors()], 422);
        }

        $email = strtolower($request->string('email')->trim()->toString());
        $user = User::where('email', $email)->first();

        // Pesan selalu sama baik email terdaftar atau tidak, supaya orang luar
        // tidak bisa memakai form ini untuk mengecek email siapa saja yang terdaftar.
        $generic = 'Jika email terdaftar, kode OTP sudah dikirim. Silakan cek kotak masuk / folder spam.';

        if ($user) {
            $otp = (string) random_int(100000, 999999);

            DB::table('password_reset_otps')->where('email', $email)->delete();
            DB::table('password_reset_otps')->insert([
                'email' => $email,
                'otp' => Hash::make($otp),
                'expires_at' => now()->addMinutes(10),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Mail::to($email)->send(new OtpResetPasswordMail($user->nama, $otp));
        }

        return response()->json(['message' => $generic]);
    }

    /** POST /api/auth/verify-otp — Body: { email, otp } — dipanggil sebelum tampilkan form password baru. */
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'otp' => ['required', 'string'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid.', 'errors' => $validator->errors()], 422);
        }

        if (! $this->validOtpRow($request->string('email')->toString(), $request->string('otp')->toString())) {
            return response()->json(['message' => 'Kode OTP salah atau sudah kedaluwarsa.'], 422);
        }

        return response()->json(['message' => 'OTP valid.']);
    }

    /** POST /api/auth/reset-password — Body: { email, otp, password, password_confirmation } */
    public function reset(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'otp' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid.', 'errors' => $validator->errors()], 422);
        }

        $email = strtolower($request->string('email')->trim()->toString());

        if (! $this->validOtpRow($email, $request->string('otp')->toString())) {
            return response()->json(['message' => 'Kode OTP salah atau sudah kedaluwarsa.'], 422);
        }

        $user = User::where('email', $email)->first();
        abort_unless($user, 404, 'Akun tidak ditemukan.');

        $user->update(['password' => Hash::make($request->input('password'))]);
        DB::table('password_reset_otps')->where('email', $email)->delete();

        return response()->json(['message' => 'Password berhasil diubah. Silakan login dengan password baru.']);
    }

    private function validOtpRow(string $email, string $otp): bool
    {
        $email = strtolower(trim($email));
        $row = DB::table('password_reset_otps')
            ->where('email', $email)
            ->where('expires_at', '>', now())
            ->orderByDesc('id')
            ->first();

        return $row && Hash::check($otp, $row->otp);
    }
}
