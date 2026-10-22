<?php

namespace App\Http\Controllers\Api\Guru;

use App\Http\Controllers\Api\Guru\Concerns\ResolvesPenugasan;
use App\Http\Controllers\Controller;
use App\Models\KomponenNilai;
use App\Models\NilaiKomponenMurid;
use App\Services\KomponenNilaiCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * CRUD komponen_nilai (Bagian 5 — pengganti bobot tugas/kuis/absensi/ujian
 * yang kaku) + matriks murid x komponen untuk tampilan spreadsheet nilai.
 */
class KomponenNilaiController extends Controller
{
    use ResolvesPenugasan;

    public function __construct(private KomponenNilaiCalculator $calculator) {}

    /** GET /guru/komponen-nilai?mapel_id= */
    public function index(Request $request)
    {
        $mapelId = $this->resolveMapelId($request);

        return response()->json(['data' => KomponenNilai::where('mapel_id', $mapelId)->orderBy('urutan')->get()]);
    }

    public function store(Request $request)
    {
        $mapelId = $this->resolveMapelId($request);

        $validator = Validator::make($request->all(), [
            'nama' => ['required', 'string', 'max:100'],
            'tipe' => ['required', 'in:manual,auto_tugas,auto_absensi'],
            'bobot_persen' => ['required', 'numeric', 'min:0', 'max:100'],
            'urutan' => ['sometimes', 'integer', 'min:0'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid.', 'errors' => $validator->errors()], 422);
        }

        $urutan = $request->integer('urutan', KomponenNilai::where('mapel_id', $mapelId)->count());

        $komponen = KomponenNilai::create([
            'mapel_id' => $mapelId,
            'nama' => $request->nama,
            'tipe' => $request->tipe,
            'bobot_persen' => $request->bobot_persen,
            'urutan' => $urutan,
        ]);

        return response()->json(['data' => $komponen], 201);
    }

    public function update(Request $request, KomponenNilai $komponenNilai)
    {
        $this->authorizeKomponen($request, $komponenNilai);

        $validator = Validator::make($request->all(), [
            'nama' => ['sometimes', 'string', 'max:100'],
            'tipe' => ['sometimes', 'in:manual,auto_tugas,auto_absensi'],
            'bobot_persen' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'urutan' => ['sometimes', 'integer', 'min:0'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid.', 'errors' => $validator->errors()], 422);
        }

        $komponenNilai->update($validator->validated());

        return response()->json(['data' => $komponenNilai]);
    }

    public function destroy(Request $request, KomponenNilai $komponenNilai)
    {
        $this->authorizeKomponen($request, $komponenNilai);
        $komponenNilai->delete();

        return response()->json(['message' => 'Komponen nilai dihapus.']);
    }

    /**
     * GET /guru/nilai-komponen/matriks?mapel_id=&kelas_id= — data spreadsheet:
     * daftar komponen + per-murid nilai tiap komponen (efektif, sudah
     * dihitung untuk tipe auto) + nilai akhir hasil hitungan sementara
     * (live, BELUM disimpan ke nilai_akhir — guru tetap harus klik "Hitung
     * Nilai" per murid seperti alur approval yang sudah ada supaya
     * tersimpan & bisa dikirim ke admin).
     */
    public function matriks(Request $request)
    {
        ['mapel_id' => $mapelId, 'kelas_id' => $kelasId] = $this->resolvePenugasan($request);

        $muridIds = \App\Models\MuridProfile::where('kelas_id', $kelasId)->pluck('user_id');

        $hasil = $this->calculator->matriks($mapelId, $muridIds);

        $murid = \App\Models\User::whereIn('id', $muridIds)->get(['id', 'nama', 'nis'])->keyBy('id');

        $baris = collect($hasil['baris'])->map(function ($b) use ($murid) {
            $b['murid'] = $murid->get($b['murid_id']);

            return $b;
        })->values();

        return response()->json(['data' => [
            'komponen' => $hasil['komponen'],
            'bobot_total' => $hasil['bobot_total'],
            'baris' => $baris,
        ]]);
    }

    /**
     * PUT /guru/nilai-komponen/bulk — simpan banyak nilai komponen manual
     * sekaligus, pola sama seperti bulk absensi yang sudah ada.
     * Body: { data: [{ komponen_nilai_id, murid_id, nilai, catatan? }] }
     */
    public function simpanBulk(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'data' => ['required', 'array', 'min:1'],
            'data.*.komponen_nilai_id' => ['required', 'exists:komponen_nilai,id'],
            'data.*.murid_id' => ['required', 'exists:users,id'],
            'data.*.nilai' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'data.*.catatan' => ['nullable', 'string'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid.', 'errors' => $validator->errors()], 422);
        }

        foreach ($request->input('data') as $row) {
            $komponen = KomponenNilai::find($row['komponen_nilai_id']);
            $this->authorizeKomponen($request, $komponen);
            abort_if($komponen->tipe !== 'manual', 422, "Komponen \"{$komponen->nama}\" bukan tipe manual, nilainya dihitung otomatis.");

            NilaiKomponenMurid::updateOrCreate(
                ['komponen_nilai_id' => $row['komponen_nilai_id'], 'murid_id' => $row['murid_id']],
                ['nilai' => $row['nilai'] ?? null, 'catatan' => $row['catatan'] ?? null]
            );
        }

        return response()->json(['message' => 'Nilai komponen tersimpan.']);
    }

    /** Guru login mengampu mapel milik komponen ini — pakai method yang sama dengan resolveMapelId() (trait ResolvesPenugasan). */
    private function authorizeKomponen(Request $request, KomponenNilai $komponen): void
    {
        abort_unless($this->adaPenugasanAktifMapel($request, $komponen->mapel_id), 403, 'Anda tidak mengampu mapel ini.');
    }
}
