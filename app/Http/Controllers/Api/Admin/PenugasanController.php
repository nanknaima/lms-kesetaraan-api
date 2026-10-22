<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\Mapel;
use App\Models\PenugasanMengajar;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Kelola Penugasan Mengajar (admin) — model baru pengganti "1 guru = 1
 * mapel": admin bisa menugaskan langsung, atau menyetujui/menolak pengajuan
 * mandiri guru (lihat Api\Guru\PenugasanController untuk sisi guru).
 */
class PenugasanController extends Controller
{
    /** GET /api/admin/penugasan?status=&guru_id=&kelas_id= */
    public function index(Request $request)
    {
        $penugasan = PenugasanMengajar::with(['guru:id,nama,nip', 'kelas:id,nama_kelas,paket,tingkat', 'mapel:id,nama_mapel,kode,paket'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('guru_id'), fn ($q) => $q->where('guru_id', $request->integer('guru_id')))
            ->when($request->filled('kelas_id'), fn ($q) => $q->where('kelas_id', $request->integer('kelas_id')))
            ->latest()
            ->get();

        return response()->json(['data' => $penugasan]);
    }

    /** Dipanggil sebelum store() & setujui() — lihat PenugasanMengajar::adaKonflikAktif(). */
    private function abortJikaBentrok(int $kelasId, int $mapelId, ?int $kecualiId = null): void
    {
        $bentrok = PenugasanMengajar::adaKonflikAktif($kelasId, $mapelId, $kecualiId);

        abort_if($bentrok, 422, 'Kelas & mapel ini sudah diampu guru lain yang aktif. Cabut dulu penugasan lama sebelum menambah yang baru.');
    }

    /** POST /api/admin/penugasan {guru_id, kelas_id, mapel_id} */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'guru_id' => ['required', 'integer', 'exists:users,id'],
            'kelas_id' => ['required', 'integer', 'exists:kelas,id'],
            'mapel_id' => ['required', 'integer', 'exists:mapel,id'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid.', 'errors' => $validator->errors()], 422);
        }

        $guru = User::find($request->integer('guru_id'));
        abort_unless($guru->role === 'guru', 422, 'Akun yang dipilih bukan akun guru.');

        $kelas = Kelas::find($request->integer('kelas_id'));
        $mapel = Mapel::find($request->integer('mapel_id'));
        abort_unless($kelas->paket === $mapel->paket, 422, 'Kelas dan mapel ini beda paket, tidak bisa dipasangkan.');

        $this->abortJikaBentrok($request->integer('kelas_id'), $request->integer('mapel_id'));

        $penugasan = PenugasanMengajar::create([
            'guru_id' => $request->integer('guru_id'),
            'kelas_id' => $request->integer('kelas_id'),
            'mapel_id' => $request->integer('mapel_id'),
            'status' => 'disetujui',
            'diajukan_oleh' => 'admin',
        ]);

        return response()->json(['data' => $penugasan->load(['guru:id,nama', 'kelas:id,nama_kelas,paket', 'mapel:id,nama_mapel'])], 201);
    }

    /** POST /api/admin/penugasan/{penugasan}/setujui — hanya untuk pengajuan guru yang masih 'diajukan'. */
    public function setujui(Request $request, PenugasanMengajar $penugasan)
    {
        abort_unless($penugasan->status === 'diajukan', 422, 'Hanya pengajuan berstatus "diajukan" yang bisa disetujui.');

        $this->abortJikaBentrok($penugasan->kelas_id, $penugasan->mapel_id, $penugasan->id);

        $penugasan->update(['status' => 'disetujui']);

        return response()->json(['data' => $penugasan->fresh()->load(['guru:id,nama', 'kelas:id,nama_kelas,paket', 'mapel:id,nama_mapel'])]);
    }

    /** POST /api/admin/penugasan/{penugasan}/tolak {catatan_admin} */
    public function tolak(Request $request, PenugasanMengajar $penugasan)
    {
        $validator = Validator::make($request->all(), [
            'catatan_admin' => ['required', 'string'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Alasan penolakan wajib diisi.', 'errors' => $validator->errors()], 422);
        }

        abort_unless($penugasan->status === 'diajukan', 422, 'Hanya pengajuan berstatus "diajukan" yang bisa ditolak.');

        $penugasan->update([
            'status' => 'ditolak',
            'catatan_admin' => $request->input('catatan_admin'),
        ]);

        return response()->json(['data' => $penugasan->fresh()]);
    }

    /** DELETE /api/admin/penugasan/{penugasan} — cabut penugasan aktif (soft: ubah status, bukan hard delete). */
    public function destroy(PenugasanMengajar $penugasan)
    {
        abort_unless($penugasan->status === 'disetujui', 422, 'Hanya penugasan aktif (disetujui) yang bisa dicabut.');

        $penugasan->update(['status' => 'dicabut']);

        return response()->json(['message' => 'Penugasan dicabut.', 'data' => $penugasan->fresh()]);
    }
}
