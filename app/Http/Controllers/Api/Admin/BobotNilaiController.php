<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\BobotNilaiConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/** Konfigurasi bobot nilai akhir (default global / per mapel). */
class BobotNilaiController extends Controller
{
    public function show(Request $request)
    {
        $config = BobotNilaiConfig::firstOrCreate(
            ['mapel_id' => $request->mapel_id],
            ['bobot_tugas' => 30, 'bobot_kuis' => 30, 'bobot_absensi' => 10, 'bobot_ujian' => 30, 'passing_grade' => 75]
        );

        return response()->json(['data' => $config]);
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mapel_id' => ['nullable', 'exists:mapel,id'],
            'bobot_tugas' => ['required', 'numeric', 'min:0', 'max:100'],
            'bobot_kuis' => ['required', 'numeric', 'min:0', 'max:100'],
            'bobot_absensi' => ['required', 'numeric', 'min:0', 'max:100'],
            'bobot_ujian' => ['required', 'numeric', 'min:0', 'max:100'],
            'passing_grade' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid.', 'errors' => $validator->errors()], 422);
        }

        $total = $request->bobot_tugas + $request->bobot_kuis + $request->bobot_absensi + $request->bobot_ujian;
        if (abs($total - 100) > 0.01) {
            return response()->json(['message' => 'Total bobot harus 100%.'], 422);
        }

        $config = BobotNilaiConfig::updateOrCreate(['mapel_id' => $request->mapel_id], $validator->validated());

        return response()->json(['data' => $config]);
    }
}
