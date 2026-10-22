<?php

namespace App\Http\Controllers\Api\Murid;

use App\Http\Controllers\Controller;
use App\Models\Mapel;
use App\Models\MuridProfile;
use App\Models\User;
use Illuminate\Http\Request;

/** Daftar & detail mapel yang relevan untuk murid login, sesuai paket ia terdaftar. */
class MapelController extends Controller
{
    public function index(Request $request)
    {
        $profile = $request->user()->muridProfile;
        abort_if(! $profile, 422, 'Murid belum terdaftar.');

        $mapel = Mapel::where('paket', $profile->paket)->get();
        $mapel->each(function (Mapel $m) use ($profile) {
            $m->setAttribute('guru', $this->guruUntukResponse($m, $profile->kelas_id));
        });

        return response()->json(['data' => $mapel]);
    }

    /**
     * GET /api/murid/mapel/{mapel} — detail "kelas" satu mapel: dipakai sebagai
     * header di halaman mapel murid (kode, dosen pengajar, jumlah peserta,
     * periode akademik). Jumlah peserta dihitung dari teman sekelas murid
     * (murid_profile.kelas_id yang sama); periode akademik dihitung dari
     * tanggal server saat ini (Jul-Des = Ganjil, Jan-Jun = Genap) — sekolah ini
     * belum punya konsep "tahun ajaran" tersimpan di database per mapel/kelas.
     */
    public function show(Request $request, Mapel $mapel)
    {
        $profile = $request->user()->muridProfile;
        abort_unless($profile && $profile->paket === $mapel->paket, 403, 'Anda tidak terdaftar di mapel ini.');

        $jumlahPeserta = $profile->kelas_id
            ? MuridProfile::where('kelas_id', $profile->kelas_id)->count()
            : MuridProfile::where('paket', $mapel->paket)->count();

        $bulan = (int) now()->format('n');
        $semester = $bulan >= 7 ? 'Ganjil' : 'Genap';
        $tahunAjaran = $bulan >= 7
            ? now()->format('Y').'/'.((int) now()->format('Y') + 1)
            : ((int) now()->format('Y') - 1).'/'.now()->format('Y');

        return response()->json(['data' => [
            'id' => $mapel->id,
            'nama_mapel' => $mapel->nama_mapel,
            'kode' => $mapel->kode,
            'paket' => $mapel->paket,
            'guru' => $this->guruUntukResponse($mapel, $profile->kelas_id),
            'jumlah_peserta' => $jumlahPeserta,
            'periode_akademik' => "{$tahunAjaran} {$semester}",
        ]]);
    }

    /**
     * Guru yang MEMANG mengampu mapel ini untuk kelas murid ybs (lewat
     * penugasan_guru) — benar walau satu mapel diampu beberapa guru di
     * kelas berbeda, beda dari relasi lama Mapel::guru() (hasOne, cuma
     * ambil satu baris guru_profile sembarang tanpa peduli kelas). Bentuk
     * responsnya sengaja disamakan dengan shape GuruProfile lama supaya
     * model GuruRingkas di mobile tidak perlu berubah.
     */
    private function guruUntukResponse(Mapel $mapel, ?int $kelasId): ?array
    {
        $guru = $kelasId ? $mapel->guruUntukKelas($kelasId) : null;
        $guru ??= $mapel->guru?->user; // fallback ke skema lama bila belum ada penugasan utk kelas ini

        if (! $guru instanceof User) {
            return null;
        }

        return [
            'user_id' => $guru->id,
            'no_hp' => $guru->no_hp,
            'user' => ['id' => $guru->id, 'nama' => $guru->nama, 'avatar_url' => $guru->avatar_url],
        ];
    }
}
