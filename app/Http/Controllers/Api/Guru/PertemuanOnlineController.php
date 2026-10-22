<?php

namespace App\Http\Controllers\Api\Guru;

use App\Http\Controllers\Api\Guru\Concerns\ResolvesPenugasan;
use App\Http\Controllers\Controller;
use App\Models\Notifikasi;
use App\Models\PertemuanOnline;
use App\Services\MeetingLinkValidatorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PertemuanOnlineController extends Controller
{
    use ResolvesPenugasan;

    public function __construct(private MeetingLinkValidatorService $linkValidator) {}

    /** GET /api/guru/pertemuan-online */
    public function index(Request $request)
    {
        $guru = $request->user();

        $data = PertemuanOnline::where('guru_id', $guru->id)
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->kelas_id, fn ($q) => $q->where('kelas_id', $request->kelas_id))
            ->when($request->mapel_id, fn ($q) => $q->where('mapel_id', $request->mapel_id))
            ->orderByDesc('tanggal')->orderByDesc('jam_mulai')
            ->paginate($request->integer('per_page', 15));

        // join_aktif tidak otomatis ikut serialisasi default model — tanpa ini tombol
        // "Gabung" di halaman guru selalu tampil nonaktif (beda dari upcoming() yang
        // sudah menyertakannya), padahal guru gabung langsung pakai link_meeting sendiri
        // tanpa perlu lewat endpoint join murid.
        $data->getCollection()->transform(fn (PertemuanOnline $p) => $p->toApiArray());

        return response()->json(['data' => $data]);
    }

    /** POST /api/guru/pertemuan-online */
    public function store(Request $request)
    {
        $guru = $request->user();

        $validator = Validator::make($request->all(), [
            'penugasan_id' => ['sometimes', 'integer', 'exists:penugasan_guru,id'],
            'kelas_id' => ['sometimes', 'integer', 'exists:kelas,id'],
            'judul' => ['required', 'string', 'max:255'],
            'platform' => ['required', 'in:zoom,google_meet,lainnya'],
            'link_meeting' => ['required', 'url'],
            'tanggal' => ['required', 'date', 'after_or_equal:today'],
            'jam_mulai' => ['required', 'date_format:H:i'],
            'jam_selesai' => ['required', 'date_format:H:i', 'after:jam_mulai'],
            'agenda' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid.', 'errors' => $validator->errors()], 422);
        }

        ['mapel_id' => $mapelId, 'kelas_id' => $kelasId] = $this->resolvePenugasan($request);

        if (! $this->linkValidator->isValidForPlatform($request->link_meeting, $request->platform)) {
            return response()->json([
                'message' => 'Link tidak sesuai format platform yang dipilih.',
                'errors' => ['link_meeting' => ["Format link tidak cocok dengan platform {$request->platform}."]],
            ], 422);
        }

        $pertemuan = PertemuanOnline::create([
            'mapel_id' => $mapelId,
            'kelas_id' => $kelasId,
            'guru_id' => $guru->id,
            'judul' => $request->judul,
            'link_meeting' => $request->link_meeting,
            'platform' => $request->platform,
            'tanggal' => $request->tanggal,
            'jam_mulai' => $request->jam_mulai,
            'jam_selesai' => $request->jam_selesai,
            'agenda' => $request->agenda,
            'status' => 'terjadwal',
        ]);

        Notifikasi::kirimKeKelas(
            $kelasId,
            'pertemuan',
            "Pertemuan online baru: {$pertemuan->judul}",
            'Jadwal: '.\Carbon\Carbon::parse($request->tanggal)->format('d/m/Y')." {$request->jam_mulai}–{$request->jam_selesai}",
            '/murid/dashboard'
        );

        // Reminder H-1 & H-15m dikirim oleh App\Console\Commands\CheckUpcomingMeetings
        // (dijadwalkan setiap menit — lihat routes/console.php).

        return response()->json(['data' => $pertemuan], 201);
    }

    /** PUT /api/guru/pertemuan-online/{pertemuan} */
    public function update(Request $request, PertemuanOnline $pertemuan)
    {
        abort_unless($pertemuan->guru_id === $request->user()->id, 403);

        $validator = Validator::make($request->all(), [
            'judul' => ['sometimes', 'string', 'max:255'],
            'tanggal' => ['sometimes', 'date'],
            'jam_mulai' => ['sometimes', 'date_format:H:i'],
            'jam_selesai' => ['sometimes', 'date_format:H:i', 'after:jam_mulai'],
            'agenda' => ['nullable', 'string'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid.', 'errors' => $validator->errors()], 422);
        }

        $pertemuan->update($validator->validated());

        return response()->json(['data' => $pertemuan]);
    }

    /** POST /api/guru/pertemuan-online/{pertemuan}/batalkan */
    public function batalkan(Request $request, PertemuanOnline $pertemuan)
    {
        abort_unless($pertemuan->guru_id === $request->user()->id, 403);
        $pertemuan->update(['status' => 'batal']);

        return response()->json(['data' => $pertemuan]);
    }

    /**
     * DELETE /api/guru/pertemuan-online/{pertemuan} — hapus dari riwayat kapan
     * saja (tidak dibatasi harus sudah selesai — guru bisa hapus pertemuan yang
     * salah jadwal/dibuat keliru tanpa harus menunggu). Kepemilikan tetap dicek
     * di backend supaya guru lain tidak bisa hapus pertemuan yang bukan miliknya.
     */
    public function destroy(Request $request, PertemuanOnline $pertemuan)
    {
        abort_unless($pertemuan->guru_id === $request->user()->id, 403);

        $pertemuan->delete();

        return response()->json(['message' => 'Pertemuan dihapus.']);
    }

    /** GET /api/pertemuan-online/upcoming — Guru & Murid (aturan bisnis #10 dicek di sini juga). */
    public function upcoming(Request $request)
    {
        $user = $request->user();

        $query = PertemuanOnline::where('status', 'terjadwal')
            ->where('tanggal', '>=', now()->toDateString())
            ->orderBy('tanggal')->orderBy('jam_mulai');

        if ($user->isGuru()) {
            $query->where('guru_id', $user->id);
        } elseif ($user->isMurid()) {
            $query->where('kelas_id', $user->muridProfile?->kelas_id);
        }

        // Pertemuan yang jam_selesai-nya sudah lewat DIBUANG dulu, baru diambil 10 teratas.
        // Sebelumnya yang sudah selesai (hari ini) ikut terurut duluan & memenuhi slot
        // daftar, sehingga pertemuan yang sedang aktif tergeser keluar dari 2 kartu
        // "Jadwal Terdekat" di dashboard murid dan tombol Join tidak pernah kelihatan.
        $data = $query->get()
            ->reject(fn (PertemuanOnline $p) => $p->sudahSelesai())
            ->take(10)
            ->map(fn (PertemuanOnline $p) => $p->toApiArray())
            ->values();

        return response()->json(['data' => $data]);
    }
}
