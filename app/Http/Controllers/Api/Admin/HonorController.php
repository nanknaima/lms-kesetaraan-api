<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\HonorGuru;
use App\Models\TransaksiKeuangan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class HonorController extends Controller
{
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'periode' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Filter tidak valid.', 'errors' => $validator->errors()], 422);
        }

        $honor = HonorGuru::with('guru:id,nama,nip')
            ->when($request->periode, fn ($q) => $q->where('periode', $request->periode))
            ->when($request->search, fn ($q) => $q->whereHas('guru', fn ($guru) => $guru
                ->where('nama', 'like', '%'.$request->search.'%')
                ->orWhere('nip', 'like', '%'.$request->search.'%')))
            ->latest('periode')
            ->latest('id')
            ->paginate($request->integer('per_page', 15));

        return response()->json(['data' => $honor]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'guru_id' => ['required', 'integer', 'exists:users,id'],
            'periode' => [
                'required',
                'regex:/^\d{4}-(0[1-9]|1[0-2])$/',
                Rule::unique('honor_guru')->where(fn ($query) => $query->where('guru_id', $request->guru_id)),
            ],
            'keterangan' => ['nullable', 'string', 'max:255'],
            'jumlah' => ['required', 'numeric', 'gt:0', 'max:9999999999999.99'],
            'mata_uang' => ['required', 'in:IDR,NTD'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Data honor tidak valid.', 'errors' => $validator->errors()], 422);
        }

        abort_unless(User::whereKey($request->guru_id)->where('role', 'guru')->exists(), 422, 'Akun yang dipilih bukan guru.');
        $honor = HonorGuru::create([
            ...$validator->validated(),
            'dibuat_oleh_id' => $request->user()->id,
        ]);

        return response()->json(['data' => $honor->load('guru:id,nama,nip')], 201);
    }

    public function update(Request $request, HonorGuru $honor)
    {
        abort_if($honor->status === 'dibayar', 422, 'Honor yang sudah dibayar tidak dapat diubah.');

        $validator = Validator::make($request->all(), [
            'keterangan' => ['nullable', 'string', 'max:255'],
            'jumlah' => ['required', 'numeric', 'gt:0', 'max:9999999999999.99'],
            'mata_uang' => ['required', 'in:IDR,NTD'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Data honor tidak valid.', 'errors' => $validator->errors()], 422);
        }

        $honor = DB::transaction(function () use ($honor, $validator) {
            $locked = HonorGuru::whereKey($honor->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->status === 'dibayar', 422, 'Honor yang sudah dibayar tidak dapat diubah.');
            $locked->update($validator->validated());

            return $locked->fresh()->load('guru:id,nama,nip');
        });

        return response()->json(['data' => $honor]);
    }

    public function bayar(Request $request, HonorGuru $honor)
    {
        $validator = Validator::make($request->all(), [
            'tanggal_bayar' => ['required', 'date'],
            'metode_bayar' => ['required', 'in:tunai,transfer,lainnya'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Data pembayaran honor tidak valid.', 'errors' => $validator->errors()], 422);
        }

        $honor = DB::transaction(function () use ($request, $honor, $validator) {
            $locked = HonorGuru::whereKey($honor->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->status === 'dibayar', 422, 'Honor ini sudah dibayar.');

            $locked->update([
                ...$validator->validated(),
                'status' => 'dibayar',
                'dibayar_oleh_id' => $request->user()->id,
            ]);

            TransaksiKeuangan::create([
                'jenis' => 'pengeluaran',
                'sumber' => 'honor',
                'keterangan' => 'Pembayaran honor '.$locked->guru()->value('nama').' - '.$locked->periode,
                'jumlah' => $locked->jumlah,
                'mata_uang' => $locked->mata_uang,
                'tanggal' => $locked->tanggal_bayar,
                'honor_guru_id' => $locked->id,
                'dicatat_oleh_id' => $request->user()->id,
            ]);

            return $locked->fresh()->load('guru:id,nama,nip');
        });

        return response()->json(['data' => $honor]);
    }
}
