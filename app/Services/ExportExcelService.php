<?php

namespace App\Services;

use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExportExcelService
{
    /**
     * Generate .xlsx dari array asosiatif dan langsung stream sebagai download.
     * $rows = [ ['Kolom A' => ..., 'Kolom B' => ...], ... ]
     */
    public function rekapTabel(string $judul, array $rows, string $filename)
    {
        $export = new class($judul, $rows) implements FromArray, WithHeadings, WithStyles, WithTitle
        {
            public function __construct(private string $judul, private array $rows) {}

            public function array(): array
            {
                return array_map('array_values', $this->rows);
            }

            public function headings(): array
            {
                return $this->rows ? array_keys($this->rows[0]) : [];
            }

            public function title(): string
            {
                return substr($this->judul, 0, 31); // batas nama sheet Excel
            }

            public function styles(Worksheet $sheet): array
            {
                return [1 => ['font' => ['bold' => true]]]; // header bold — styling sesuai spek
            }
        };

        return Excel::download($export, $filename);
    }
}
