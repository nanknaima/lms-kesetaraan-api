<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\TransaksiKeuangan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class KeuanganController extends Controller
{
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date', 'after_or_equal:dari'],
            'mata_uang' => ['nullable', 'in:IDR,NTD'],
            'jenis' => ['nullable', 'in:pemasukan,pengeluaran'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Filter tidak valid.', 'errors' => $validator->errors()], 422);
        }

        $query = TransaksiKeuangan::query()
            ->when($request->dari, fn ($q) => $q->whereDate('tanggal', '>=', $request->dari))
            ->when($request->sampai, fn ($q) => $q->whereDate('tanggal', '<=', $request->sampai))
            ->when($request->mata_uang, fn ($q) => $q->where('mata_uang', $request->mata_uang));

        $ringkasan = (clone $query)
            ->selectRaw("mata_uang, SUM(CASE WHEN jenis = 'pemasukan' THEN jumlah ELSE 0 END) as pemasukan")
            ->selectRaw("SUM(CASE WHEN jenis = 'pengeluaran' THEN jumlah ELSE 0 END) as pengeluaran")
            ->groupBy('mata_uang')
            ->get()
            ->map(fn ($item) => [
                'mata_uang' => $item->mata_uang,
                'pemasukan' => $item->pemasukan,
                'pengeluaran' => $item->pengeluaran,
                'saldo' => number_format(
                    ($this->minorUnits($item->pemasukan) - $this->minorUnits($item->pengeluaran)) / 100,
                    2,
                    '.',
                    ''
                ),
            ]);

        $transaksi = (clone $query)
            ->when($request->jenis, fn ($q) => $q->where('jenis', $request->jenis))
            ->with('pembayaranMurid.tagihan.murid:id,nama', 'honorGuru.guru:id,nama')
            ->latest('tanggal')
            ->latest('id')
            ->paginate($request->integer('per_page', 20));

        return response()->json(['data' => $transaksi, 'ringkasan' => $ringkasan]);
    }

    private function minorUnits(string|int|float $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'jenis' => ['required', 'in:pemasukan,pengeluaran'],
            'keterangan' => ['required', 'string', 'max:255'],
            'jumlah' => ['required', 'numeric', 'gt:0', 'max:9999999999999.99'],
            'mata_uang' => ['required', 'in:IDR,NTD'],
            'tanggal' => ['required', 'date'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Data transaksi tidak valid.', 'errors' => $validator->errors()], 422);
        }

        $transaksi = TransaksiKeuangan::create([
            ...$validator->validated(),
            'sumber' => 'manual',
            'dicatat_oleh_id' => $request->user()->id,
        ]);

        return response()->json(['data' => $transaksi], 201);
    }
}
