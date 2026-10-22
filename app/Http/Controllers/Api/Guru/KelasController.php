<?php

namespace App\Http\Controllers\Api\Guru;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\PenugasanMengajar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Daftar kelas yang relevan untuk guru login, lengkap dengan roster murid per
 * kelas — dipakai sebagai pilihan "kelas_id" saat membuat Materi/Tugas/Kuis/
 * Pertemuan Online (jalur lama/Web, guru single-mapel), dan sebagai daftar
 * murid saat input Absensi. Kelas & Mapel terhubung lewat kolom `paket` yang
 * sama (Mapel tidak punya kolom kelas_id langsung). Response TIDAK diubah
 * (masih dipakai mobile) — hanya SUMBER kelas_id yang diperluas supaya guru
 * dengan penugasan di model baru (Penugasan Mengajar) juga muncul, bukan cuma
 * yang mengampu lewat guru_profile.mapel_id atau penugasan_guru (interim).
 */
class KelasController extends Controller
{
    public function index(Request $request)
    {
        $guru = $request->user();
        $mapel = $guru->mapelDiampu();

        // Kelas dari penugasan spesifik guru ini — model baru (Penugasan
        // Mengajar) DAN interim (penugasan_guru, dipakai mobile) — digabung
        // supaya tidak ada yang hilang dari kedua sumber.
        $kelasIdsPenugasan = PenugasanMengajar::aktif()->where('guru_id', $guru->id)->pluck('kelas_id')
            ->merge($guru->penugasan()->distinct()->pluck('kelas_id'))
            ->unique();

        if ($mapel) {
            // Guru single-mapel (jalur lama, dipakai Web) — semua kelas di
            // paket itu, digabung kelas spesifik dari penugasan (mis. guru
            // ini JUGA punya penugasan baru di paket lain).
            $kelas = Kelas::where(function ($q) use ($mapel, $kelasIdsPenugasan) {
                $q->where('paket', $mapel->paket)->orWhereIn('id', $kelasIdsPenugasan);
            })
                ->with('murid.user:id,nama,nis')
                ->get(['id', 'nama_kelas', 'paket', 'tingkat']);
        } else {
            // Guru dengan banyak penugasan — hanya kelas yang sudah menjadi penugasan aktifnya.
            $kelas = Kelas::whereIn('id', $kelasIdsPenugasan)
                ->with('murid.user:id,nama,nis')
                ->get(['id', 'nama_kelas', 'paket', 'tingkat']);
        }

        return response()->json(['data' => $kelas]);
    }

    /**
     * POST /guru/kelas — guru bisa menambah kelas sendiri ("Kelas Lainnya")
     * bila kelas yang dibutuhkan belum ada di daftar (mis. admin belum
     * membuatkannya). Paket & rentang tingkat mengikuti struktur kesetaraan:
     * Paket A tingkat 1-6, Paket B & C tingkat 1-3.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'paket' => ['required', 'in:A,B,C'],
            'tingkat' => ['required', 'string', 'max:10'],
            'nama_kelas' => ['nullable', 'string', 'max:255'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid.', 'errors' => $validator->errors()], 422);
        }

        $namaKelas = $request->nama_kelas ?: "Paket {$request->paket} - Kelas {$request->tingkat}";

        $kelas = Kelas::create([
            'nama_kelas' => $namaKelas,
            'paket' => $request->paket,
            'tingkat' => $request->tingkat,
        ]);

        return response()->json(['data' => $kelas], 201);
    }
}
