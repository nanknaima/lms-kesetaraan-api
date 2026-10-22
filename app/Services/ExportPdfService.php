<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;

class ExportPdfService
{
    /**
     * Generate PDF rekap tabel dengan kop sekolah (view: resources/views/exports/rekap-tabel.blade.php).
     * View menerima $judul, $rows (array asosiatif), $tanggalCetak.
     */
    public function rekapTabel(string $judul, array $rows, string $filename)
    {
        $pdf = Pdf::loadView('exports.rekap-tabel', [
            'judul' => $judul,
            'kolom' => $rows ? array_keys($rows[0]) : [],
            'rows' => $rows,
            'tanggalCetak' => now()->translatedFormat('d F Y H:i'),
        ])->setPaper('a4', 'portrait');

        return $pdf->download($filename);
    }
}
