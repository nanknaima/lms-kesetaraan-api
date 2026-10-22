<?php

namespace App\Http\Controllers\Api\Murid;

use App\Http\Controllers\Controller;
use App\Models\JawabanKuis;
use App\Models\Kuis;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class KuisKerjakanController extends Controller
{
    public function index(Request $request)
    {
        $kelasId = $request->user()->muridProfile?->kelas_id;

        return response()->json(['data' => Kuis::where('kelas_id', $kelasId)
            ->when($request->mapel_id, fn ($q) => $q->where('mapel_id', $request->mapel_id))
            ->where('selesai', '>=', now())->latest('mulai')->get()]);
    }

    /** Mulai kuis: soal diacak jika random_soal=true (aturan bisnis: "random soal"). */
    public function mulai(Request $request, Kuis $kuis)
    {
        abort_unless($kuis->kelas_id === $request->user()->muridProfile?->kelas_id, 403);
        abort_unless(now()->between($kuis->mulai, $kuis->selesai), 422, 'Kuis belum/sudah tidak bisa dikerjakan.');

        $soal = $kuis->soal()->get(['id', 'pertanyaan', 'tipe', 'opsi', 'poin']); // jawaban_benar disembunyikan
        if ($kuis->random_soal) {
            $soal = $soal->shuffle()->values();
        }

        return response()->json(['data' => ['kuis' => $kuis, 'soal' => $soal]]);
    }

    /** Auto-save jawaban per soal (dipanggil berkali-kali selama pengerjaan). */
    public function jawab(Request $request, Kuis $kuis)
    {
        $validator = Validator::make($request->all(), [
            'soal_id' => ['required', 'exists:soal_kuis,id'],
            'jawaban' => ['required', 'string'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid.', 'errors' => $validator->errors()], 422);
        }

        $jawaban = JawabanKuis::updateOrCreate(
            ['kuis_id' => $kuis->id, 'murid_id' => $request->user()->id, 'soal_id' => $request->soal_id],
            ['jawaban' => $request->jawaban]
        );

        return response()->json(['data' => $jawaban]);
    }

    /** Submit final: auto-grade untuk pilihan_ganda & benar_salah, esai menunggu guru. */
    public function submit(Request $request, Kuis $kuis)
    {
        $murid = $request->user();

        $jawabanList = JawabanKuis::where('kuis_id', $kuis->id)->where('murid_id', $murid->id)
            ->with('soal' /* tambahkan relasi soal() di model JawabanKuis bila diperlukan */)
            ->get();

        foreach ($kuis->soal as $soal) {
            $jawaban = $jawabanList->firstWhere('soal_id', $soal->id);
            if (! $jawaban || $soal->tipe === 'esai') {
                continue; // esai dinilai manual oleh guru
            }

            $benar = trim(strtolower($jawaban->jawaban)) === trim(strtolower((string) $soal->jawaban_benar));
            $jawaban->update(['skor' => $benar ? $soal->poin : 0, 'auto_graded' => true]);
        }

        return response()->json(['message' => 'Kuis berhasil disubmit.']);
    }
}
