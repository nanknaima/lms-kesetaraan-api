<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendKredensialJob;
use App\Models\Pendaftaran;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/** CRUD akun Murid + filter Paket A/B/C & status. */
class MuridController extends Controller
{
    public function index(Request $request)
    {
        $murid = User::where('role', 'murid')
            ->with('muridProfile.kelas')
            ->when($request->paket, fn ($q) => $q->whereHas('muridProfile', fn ($q2) => $q2->where('paket', $request->paket)))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->search, fn ($q) => $q->where('nama', 'like', "%{$request->search}%"))
            ->paginate($request->integer('per_page', 15));

        return response()->json(['data' => $murid]);
    }

    /**
     * POST /api/admin/murid — buat akun murid langsung oleh admin (mis. murid lama yang
     * sudah terdaftar offline di sekolah, tanpa lewat alur Pendaftaran publik).
     * NIS & password diinput MANUAL oleh admin (bukan digenerate otomatis) karena NIS
     * resmi turun dari sistem akademik, bukan dari aplikasi ini. Password tetap
     * dikirim ke email murid & ditampilkan sekali di response, sama seperti sebelumnya
     * — cuma sumber nilainya sekarang dari input admin, bukan hasil random.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'nis' => ['required', 'string', 'max:50', 'unique:users,nis'],
            'password' => ['required', 'string', 'min:8'],
            'paket' => ['required', 'in:A,B,C'],
            'kelas_id' => ['nullable', 'exists:kelas,id'],
            'tgl_lahir' => ['nullable', 'date'],
            'alamat' => ['nullable', 'string'],
            // Opsional — diisi kalau akun ini dibuat dari pendaftaran yang sudah
            // disetujui (Bagian G), supaya baris pendaftaran itu ditandai selesai
            // dan tidak dipakai dua kali untuk membuat akun ganda.
            'pendaftaran_id' => ['nullable', 'exists:pendaftaran,id'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid.', 'errors' => $validator->errors()], 422);
        }

        if ($request->filled('pendaftaran_id')) {
            $pendaftaran = Pendaftaran::find($request->pendaftaran_id);
            abort_if($pendaftaran->status !== 'approved', 422, 'Pendaftaran ini belum disetujui.');
            abort_if($pendaftaran->user_id !== null, 422, 'Akun murid untuk pendaftaran ini sudah pernah dibuat.');
        }

        $murid = DB::transaction(function () use ($request) {
            $user = User::create([
                'nama' => $request->nama,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'murid',
                'nis' => $request->nis,
                'status' => 'aktif',
            ]);
            $user->muridProfile()->create([
                'paket' => $request->paket,
                'kelas_id' => $request->kelas_id,
                'tgl_lahir' => $request->tgl_lahir,
                'alamat' => $request->alamat,
            ]);

            if ($request->filled('pendaftaran_id')) {
                Pendaftaran::where('id', $request->pendaftaran_id)->update(['user_id' => $user->id]);
            }

            return $user;
        });

        SendKredensialJob::dispatch($murid, $request->password);

        return response()->json([
            'data' => $murid->load('muridProfile.kelas'),
            'password_awal' => $request->password,
        ], 201);
    }

    public function show(User $murid)
    {
        return response()->json(['data' => $murid->load('muridProfile.kelas')]);
    }

    public function update(Request $request, User $murid)
    {
        $murid->update($request->only(['nama', 'email']));
        if ($request->filled('kelas_id')) {
            $murid->muridProfile()->update(['kelas_id' => $request->kelas_id]);
        }

        return response()->json(['data' => $murid->fresh('muridProfile.kelas')]);
    }

    /** Nonaktifkan (mis. sudah lulus / bermasalah) — aturan bisnis #6. */
    public function destroy(Request $request, User $murid)
    {
        $murid->update(['status' => $request->input('status', 'nonaktif')]);
        $murid->delete();

        return response()->json(['message' => 'Akun murid dinonaktifkan.']);
    }
}
