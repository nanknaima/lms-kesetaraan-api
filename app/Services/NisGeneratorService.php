<?php

namespace App\Services;

use App\Models\User;

class NisGeneratorService
{
    /**
     * Aturan bisnis #3: NIS = [PAKET][TAHUN][URUT], contoh A2024001, B2024002, C2024003.
     * Urut direset tiap tahun & per paket (bukan global) agar nomor tetap pendek dan rapi.
     */
    public function generate(string $paket, ?int $tahun = null): string
    {
        $tahun ??= (int) now()->year;
        $prefix = strtoupper($paket).$tahun;

        $last = User::where('nis', 'like', "{$prefix}%")
            ->orderByDesc('nis')
            ->value('nis');

        $urut = $last ? ((int) substr($last, -3)) + 1 : 1;

        return $prefix.str_pad((string) $urut, 3, '0', STR_PAD_LEFT);
    }
}
