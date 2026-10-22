<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pengumuman;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * Pengumuman berbasis slide show (fitur BARU).
 * - Admin: CRUD penuh (index/store/update/destroy/pin) → prefix /admin/pengumuman
 * - Semua role: GET /pengumuman/slideshow (hanya tayang) & GET /pengumuman/{id} (detail)
 */
class PengumumanController extends Controller
{
    /** GET /api/admin/pengumuman */
    public function index(Request $request)
    {
        $pengumuman = Pengumuman::latest()->paginate($request->integer('per_page', 15));

        return response()->json(['data' => $pengumuman]);
    }

    /** POST /api/admin/pengumuman */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'judul' => ['required', 'string', 'max:255'],
            'konten' => ['required', 'string'],
            'gambar' => ['nullable', 'image', 'max:2048'], // max 2MB sesuai spek
            'target' => ['required', 'in:semua,paket,kelas'],
            'target_id' => ['required_unless:target,semua'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_berakhir' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'pinned' => ['boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid.', 'errors' => $validator->errors()], 422);
        }

        // Simpan URL PENUH (bukan path relatif) sejak awal — konsisten dengan pola
        // yang sudah dipakai ProfileController::updateAvatar(), supaya frontend
        // bisa langsung pakai gambar_url sebagai <img src=...> tanpa perlu tahu
        // path storage backend. Path relatif yang dipakai langsung sebagai src
        // itulah akar masalah gambar broken di slideshow (aturan bisnis #7).
        $gambarUrl = $request->hasFile('gambar')
            ? Storage::disk('public')->url($request->file('gambar')->store('pengumuman', 'public'))
            : null;

        $pengumuman = Pengumuman::create([
            'admin_id' => $request->user()->id,
            'judul' => $request->judul,
            'konten' => $request->konten,
            'gambar_url' => $gambarUrl,
            'target' => $request->target,
            'target_id' => $request->target_id,
            'tanggal_mulai' => $request->tanggal_mulai,
            'tanggal_berakhir' => $request->tanggal_berakhir,
            'pinned' => $request->boolean('pinned'),
            'status_publish' => 'published',
        ]);

        // TODO: dispatch NotifikasiJob broadcast ke user sesuai target (semua/paket/kelas)

        return response()->json(['data' => $pengumuman], 201);
    }

    /** PUT /api/admin/pengumuman/{pengumuman} */
    public function update(Request $request, Pengumuman $pengumuman)
    {
        $validator = Validator::make($request->all(), [
            'judul' => ['sometimes', 'string', 'max:255'],
            'konten' => ['sometimes', 'string'],
            'tanggal_mulai' => ['sometimes', 'date'],
            'tanggal_berakhir' => ['sometimes', 'date', 'after_or_equal:tanggal_mulai'],
            'status_publish' => ['sometimes', 'in:draft,published,expired'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid.', 'errors' => $validator->errors()], 422);
        }

        $pengumuman->update($validator->validated());

        return response()->json(['data' => $pengumuman]);
    }

    /** DELETE /api/admin/pengumuman/{pengumuman} */
    public function destroy(Pengumuman $pengumuman)
    {
        $pengumuman->delete();

        return response()->json(['message' => 'Pengumuman dihapus.']);
    }

    /** POST /api/admin/pengumuman/{pengumuman}/pin */
    public function togglePin(Pengumuman $pengumuman)
    {
        $pengumuman->update(['pinned' => ! $pengumuman->pinned]);

        return response()->json(['data' => $pengumuman]);
    }

    /**
     * GET /api/pengumuman/slideshow
     * Dipakai oleh carousel di dashboard Admin/Guru/Murid.
     * Filter target: 'semua' selalu tampil; 'paket'/'kelas' difilter sesuai profil user login.
     */
    public function slideshow(Request $request)
    {
        $user = $request->user();

        $items = Pengumuman::tayang()
            ->where(function ($q) use ($user) {
                $q->where('target', 'semua');

                if ($user->isMurid() && $user->muridProfile) {
                    $q->orWhere(function ($q2) use ($user) {
                        $q2->where('target', 'paket')->where('target_id', $user->muridProfile->paket);
                    })->orWhere(function ($q2) use ($user) {
                        $q2->where('target', 'kelas')->where('target_id', $user->muridProfile->kelas_id);
                    });
                }
            })
            ->get()
            ->map(fn (Pengumuman $p) => [
                'id' => $p->id,
                'judul' => $p->judul,
                'gambar_url' => $p->gambar_url,
                'pinned' => $p->pinned,
                'badge_baru' => $p->isBaru(),
            ]);

        return response()->json(['data' => $items]);
    }

    /** GET /api/pengumuman/{pengumuman} — halaman detail (klik dari slide). */
    public function show(Pengumuman $pengumuman)
    {
        return response()->json(['data' => $pengumuman]);
    }
}
