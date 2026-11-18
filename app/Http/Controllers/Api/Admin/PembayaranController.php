<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PembayaranMurid;
use App\Models\TagihanMurid;
use App\Models\TransaksiKeuangan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PembayaranController extends Controller
{
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'periode' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'status' => ['nullable', 'in:belum_lunas,sebagian,lunas'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Filter tidak valid.', 'errors' => $validator->errors()], 422);
        }

        $query = TagihanMurid::with('murid:id,nama,nis')
            ->withSum('pembayaran as total_dibayar', 'jumlah')
            ->when($request->periode, fn ($q) => $q->where('periode', $request->periode))
            ->when($request->search, fn ($q) => $q->whereHas('murid', fn ($murid) => $murid
                ->where('nama', 'like', '%'.$request->search.'%')
                ->orWhere('nis', 'like', '%'.$request->search.'%')))
            ->when($request->status, function ($q) use ($request) {
                $dibayar = '(select coalesce(sum(jumlah), 0) from pembayaran_murid where pembayaran_murid.tagihan_id = tagihan_murid.id)';

                match ($request->status) {
                    'lunas' => $q->whereRaw("$dibayar >= tagihan_murid.jumlah"),
                    'sebagian' => $q->whereRaw("$dibayar > 0 and $dibayar < tagihan_murid.jumlah"),
                    'belum_lunas' => $q->whereRaw("$dibayar = 0"),
                };
            })
            ->latest('periode')
            ->latest('id');

        $tagihan = $query->paginate($request->integer('per_page', 15));
        $tagihan->getCollection()->transform(fn (TagihanMurid $item) => $this->formatTagihan($item));

        return response()->json(['data' => $tagihan]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'murid_id' => ['required', 'integer', 'exists:users,id'],
            'periode' => [
                'required',
                'regex:/^\d{4}-(0[1-9]|1[0-2])$/',
                Rule::unique('tagihan_murid')->where(fn ($query) => $query->where('murid_id', $request->murid_id)),
            ],
            'keterangan' => ['nullable', 'string', 'max:255'],
            'jumlah' => ['required', 'numeric', 'gt:0', 'max:9999999999999.99'],
            'mata_uang' => ['required', 'in:IDR,NTD'],
            'jatuh_tempo' => ['nullable', 'date'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Data tagihan tidak valid.', 'errors' => $validator->errors()], 422);
        }

        abort_unless(User::whereKey($request->murid_id)->where('role', 'murid')->exists(), 422, 'Akun yang dipilih bukan murid.');
        $tagihan = TagihanMurid::create([
            ...$validator->validated(),
            'dibuat_oleh_id' => $request->user()->id,
        ]);

        return response()->json(['data' => $this->formatTagihan($tagihan->load('murid:id,nama,nis'))], 201);
    }

    public function show(TagihanMurid $tagihan)
    {
        return response()->json([
            'data' => $this->formatTagihan($tagihan->load(['murid:id,nama,nis', 'pembayaran'])),
        ]);
    }

    public function catat(Request $request, TagihanMurid $tagihan)
    {
        $validator = Validator::make($request->all(), [
            'jumlah' => ['required', 'numeric', 'gt:0', 'max:9999999999999.99'],
            'tanggal' => ['required', 'date'],
            'metode' => ['required', 'in:tunai,transfer,lainnya'],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Data pembayaran tidak valid.', 'errors' => $validator->errors()], 422);
        }

        $pembayaran = DB::transaction(function () use ($request, $tagihan, $validator) {
            $locked = TagihanMurid::whereKey($tagihan->id)->lockForUpdate()->firstOrFail();
            $jumlahTerbayar = (int) round((float) $locked->pembayaran()->sum('jumlah') * 100);
            $jumlahMasuk = (int) round((float) $validator->validated()['jumlah'] * 100);
            $jumlahTagihan = (int) round((float) $locked->jumlah * 100);
            abort_if($jumlahMasuk + $jumlahTerbayar > $jumlahTagihan, 422, 'Pembayaran melebihi sisa tagihan.');

            $pembayaran = $locked->pembayaran()->create([
                ...$validator->validated(),
                'dicatat_oleh_id' => $request->user()->id,
            ]);

            TransaksiKeuangan::create([
                'jenis' => 'pemasukan',
                'sumber' => 'pembayaran',
                'keterangan' => 'Pembayaran '.$locked->murid()->value('nama').' - '.$locked->periode,
                'jumlah' => $pembayaran->jumlah,
                'mata_uang' => $locked->mata_uang,
                'tanggal' => $pembayaran->tanggal,
                'pembayaran_murid_id' => $pembayaran->id,
                'dicatat_oleh_id' => $request->user()->id,
            ]);

            return $pembayaran;
        });

        return response()->json([
            'data' => $this->formatTagihan($tagihan->fresh()->load(['murid:id,nama,nis', 'pembayaran'])),
            'pembayaran' => $pembayaran,
        ], 201);
    }

    public function destroy(TagihanMurid $tagihan)
    {
        abort_if($tagihan->pembayaran()->exists(), 422, 'Tagihan yang sudah memiliki pembayaran tidak dapat dihapus.');
        $tagihan->delete();

        return response()->json(['message' => 'Tagihan dihapus.']);
    }

    private function formatTagihan(TagihanMurid $tagihan): array
    {
        $dibayarMinor = $tagihan->relationLoaded('pembayaran')
            ? $tagihan->pembayaran->sum(fn (PembayaranMurid $pembayaran) => $this->minorUnits($pembayaran->jumlah))
            : $this->minorUnits($tagihan->total_dibayar ?? 0);
        $jumlahMinor = $this->minorUnits($tagihan->jumlah);
        $tagihan->setAttribute('total_dibayar', number_format($dibayarMinor / 100, 2, '.', ''));
        $tagihan->setAttribute('sisa', number_format(max(0, $jumlahMinor - $dibayarMinor) / 100, 2, '.', ''));
        $tagihan->setAttribute('status_pembayaran', $dibayarMinor >= $jumlahMinor ? 'lunas' : ($dibayarMinor > 0 ? 'sebagian' : 'belum_lunas'));

        return $tagihan->toArray();
    }

    private function minorUnits(string|int|float $amount): int
    {
        return (int) round((float) $amount * 100);
    }
}
