<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pendaftaran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/** Form pendaftaran murid baru — endpoint publik, tanpa auth. */
class PendaftaranPublicController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'no_hp' => ['required', 'string'],
            'paket' => ['required', 'in:A,B,C'],
            'tgl_lahir' => ['required', 'date', 'before:today'],
            'alamat' => ['nullable', 'string'],
            'dokumen' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid.', 'errors' => $validator->errors()], 422);
        }

        // URL PENUH (bukan path relatif) sejak awal — pola sama seperti perbaikan
        // gambar_url pengumuman (aturan bisnis #7), supaya admin bisa langsung
        // pakai dokumen_url sebagai href tanpa perlu tahu path storage backend.
        $dokumenUrl = Storage::disk('public')->url($request->file('dokumen')->store('pendaftaran/dokumen', 'public'));

        $pendaftaran = Pendaftaran::create([
            'nama' => $request->nama,
            'email' => $request->email,
            'no_hp' => $request->no_hp,
            'paket' => $request->paket,
            'tgl_lahir' => $request->tgl_lahir,
            'alamat' => $request->alamat,
            'dokumen_url' => $dokumenUrl,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Pendaftaran berhasil dikirim. Silakan tunggu verifikasi admin.',
            'data' => $pendaftaran,
        ], 201);
    }
}
