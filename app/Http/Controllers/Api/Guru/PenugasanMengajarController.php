<?php

namespace App\Http\Controllers\Api\Guru;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\Mapel;
use App\Models\PenugasanMengajar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Sisi guru dari model Penugasan Mengajar — lihat sisi admin di
 * Api\Admin\PenugasanController. Sengaja dinamai berbeda dari
 * Api\Guru\PenugasanController (yang menangani penugasan_guru, skema
 * interim self-claim milik mobile) supaya rute /guru/penugasan LAMA (dipakai
 * mobile) tidak bentrok dengan endpoint baru ini — lihat routes/api.php,
 * endpoint baru dipasang di path /guru/penugasan-saya & /guru/kelas-mapel-tersedia.
 */
class PenugasanMengajarController extends Controller
{
    /** GET /api/guru/penugasan-saya — semua status, termasuk yang masih menunggu/ditolak. */
    public function index(Request $request)
    {
        $penugasan = PenugasanMengajar::where('guru_id', $request->user()->id)
            ->with(['kelas:id,nama_kelas,paket,tingkat', 'mapel:id,nama_mapel,kode,paket'])
            ->latest()
            ->get();

        return response()->json(['data' => $penugasan]);
    }

    /**
     * GET /api/guru/kelas-mapel-tersedia — semua kombinasi Kelas x Mapel
     * (satu paket), dikelompokkan per paket. Kombinasi yang sudah terisi
     * ikut ditampilkan (read-only, dengan info guru pengampu saat ini) —
     * beda dari Guru\PenugasanController::tersedia() (skema lama) yang cuma
     * menampilkan yang kosong.
     */
    public function kelasMapelTersedia()
    {
        $aktifByKombinasi = PenugasanMengajar::aktif()
            ->with('guru:id,nama')
            ->get()
            ->keyBy(fn ($p) => "{$p->kelas_id}-{$p->mapel_id}");

        $hasil = [];
        foreach (['A', 'B', 'C'] as $paket) {
            $kelasList = Kelas::where('paket', $paket)->get(['id', 'nama_kelas', 'tingkat']);
            $mapelList = Mapel::where('paket', $paket)->get(['id', 'nama_mapel', 'kode']);

            $kombinasi = [];
            foreach ($mapelList as $mapel) {
                foreach ($kelasList as $kelas) {
                    $aktif = $aktifByKombinasi->get("{$kelas->id}-{$mapel->id}");
                    $kombinasi[] = [
                        'kelas_id' => $kelas->id,
                        'nama_kelas' => $kelas->nama_kelas,
                        'tingkat' => $kelas->tingkat,
                        'mapel_id' => $mapel->id,
                        'nama_mapel' => $mapel->nama_mapel,
                        'kode_mapel' => $mapel->kode,
                        'status' => $aktif ? 'terisi' : 'kosong',
                        'guru_pengampu' => $aktif?->guru ? ['id' => $aktif->guru->id, 'nama' => $aktif->guru->nama] : null,
                    ];
                }
            }
            $hasil[$paket] = $kombinasi;
        }

        return response()->json(['data' => $hasil]);
    }

    /** POST /api/guru/penugasan-saya {kelas_id, mapel_id, catatan_guru?} — ajukan diri, menunggu persetujuan admin. */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'kelas_id' => ['required', 'integer', 'exists:kelas,id'],
            'mapel_id' => ['required', 'integer', 'exists:mapel,id'],
            'catatan_guru' => ['nullable', 'string'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid.', 'errors' => $validator->errors()], 422);
        }

        $kelasId = $request->integer('kelas_id');
        $mapelId = $request->integer('mapel_id');

        $kelas = Kelas::find($kelasId);
        $mapel = Mapel::find($mapelId);
        abort_unless($kelas->paket === $mapel->paket, 422, 'Kelas dan mapel ini beda paket, tidak bisa dipasangkan.');

        abort_if(
            PenugasanMengajar::adaKonflikAktif($kelasId, $mapelId),
            422,
            'Kelas & mapel ini sudah diampu guru lain yang aktif.'
        );

        $sudahMengajukan = PenugasanMengajar::where('guru_id', $request->user()->id)
            ->where('kelas_id', $kelasId)
            ->where('mapel_id', $mapelId)
            ->where('status', 'diajukan')
            ->exists();
        abort_if($sudahMengajukan, 422, 'Anda sudah mengajukan kombinasi ini, masih menunggu persetujuan admin.');

        $penugasan = PenugasanMengajar::create([
            'guru_id' => $request->user()->id,
            'kelas_id' => $kelasId,
            'mapel_id' => $mapelId,
            'status' => 'diajukan',
            'diajukan_oleh' => 'guru',
            'catatan_guru' => $request->input('catatan_guru'),
        ]);

        return response()->json(['data' => $penugasan->load(['kelas:id,nama_kelas,paket', 'mapel:id,nama_mapel'])], 201);
    }
}
