<?php

namespace App\Http\Controllers\Api\Guru;

use App\Http\Controllers\Controller;
use App\Models\Kuis;
use App\Models\SoalKuis;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SoalKuisController extends Controller
{
    public function store(Request $request, Kuis $kuis)
    {
        abort_unless($kuis->guru_id === $request->user()->id, 403);

        $validator = Validator::make($request->all(), [
            'pertanyaan' => ['required', 'string'],
            'tipe' => ['required', 'in:pilihan_ganda,benar_salah,isian_singkat,esai'],
            'opsi' => ['required_if:tipe,pilihan_ganda', 'array'],
            'jawaban_benar' => ['required_unless:tipe,esai', 'string'],
            'poin' => ['required', 'integer', 'min:1'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid.', 'errors' => $validator->errors()], 422);
        }

        $soal = SoalKuis::create([...$validator->validated(), 'kuis_id' => $kuis->id]);
        $kuis->increment('total_soal');

        return response()->json(['data' => $soal], 201);
    }
}
