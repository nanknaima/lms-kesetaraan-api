<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatPesan;
use App\Models\MuridProfile;
use App\Models\PenugasanGuru;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;

/**
 * Chat personal (1:1) ANTAR SIAPA PUN yang punya akun LMS ini — tidak perlu
 * nomor HP. Kontak yang tersedia otomatis mengikuti hubungan sekolah lewat
 * tabel penugasan_guru (guru bisa mengampu banyak mapel & kelas, lihat
 * PenugasanController), supaya tidak jadi direktori bebas seluruh pengguna:
 *   - murid  <-> guru  : hanya guru yang punya penugasan untuk kelas murid itu.
 *   - murid  <-> murid : hanya sesama murid satu kelas (kelas_id sama).
 *   - guru   <-> guru  : bebas ke semua guru (staf pengajar, tanpa batas kelas).
 * Beda dari forum Diskusi per-mapel yang publik — pesan di sini privat,
 * hanya terlihat oleh dua pengguna yang bersangkutan.
 */
class ChatController extends Controller
{
    public function kontak(Request $request)
    {
        $user = $request->user();

        $kontak = $user->isMurid()
            ? $this->kontakUntukMurid($user)
            : ($user->isGuru() ? $this->kontakUntukGuru($user) : collect());

        $kontak = $kontak->map(function (array $k) use ($user) {
            [$a, $b] = ChatPesan::pasangan($user->id, $k['id']);
            $sayaA = $user->id === $a;
            $kolomHapusSaya = $sayaA ? 'dihapus_untuk_a_at' : 'dihapus_untuk_b_at';

            $terakhir = ChatPesan::where('pengguna_a_id', $a)->where('pengguna_b_id', $b)
                ->whereNull($kolomHapusSaya)
                ->latest('created_at')->first();

            $belumDibaca = ChatPesan::where('pengguna_a_id', $a)->where('pengguna_b_id', $b)
                ->whereNull($kolomHapusSaya)
                ->where('pengirim_id', $k['id'])->whereNull('dibaca_at')->count();

            return [
                ...$k,
                'pesan_terakhir' => $terakhir?->dihapus_untuk_semua_at ? 'Pesan telah dihapus' : $terakhir?->pesan,
                'waktu_terakhir' => $terakhir?->created_at,
                'belum_dibaca' => $belumDibaca,
            ];
        })->sortByDesc(fn ($k) => $k['waktu_terakhir']?->timestamp ?? 0)->values();

        return response()->json(['data' => $kontak]);
    }

    /** GET /chat/{lawan}?q=kata+kunci — riwayat percakapan, opsional cari kata dalam isi pesan. */
    public function pesan(Request $request, User $lawan)
    {
        $me = $request->user();
        $this->pastikanBolehChat($me, $lawan);

        [$a, $b] = ChatPesan::pasangan($me->id, $lawan->id);
        $sayaA = $me->id === $a;

        $pesan = ChatPesan::where('pengguna_a_id', $a)->where('pengguna_b_id', $b)
            // "Hapus untuk saya" cuma menyembunyikan dari sisi yang menghapus — pihak lain tetap melihatnya.
            ->when($sayaA, fn ($q) => $q->whereNull('dihapus_untuk_a_at'))
            ->when(! $sayaA, fn ($q) => $q->whereNull('dihapus_untuk_b_at'))
            ->when($request->filled('q'), fn ($q) => $q->where('pesan', 'like', '%'.$request->input('q').'%'))
            ->orderBy('created_at')
            ->get(['id', 'pengirim_id', 'pesan', 'pinned', 'dibaca_at', 'dihapus_untuk_semua_at', 'created_at'])
            ->map(function (ChatPesan $p) {
                // Dihapus untuk semua orang — isi asli tidak dikirim ke frontend sama sekali, cuma penanda.
                if ($p->dihapus_untuk_semua_at) {
                    $p->pesan = null;
                }

                return $p;
            });

        ChatPesan::where('pengguna_a_id', $a)->where('pengguna_b_id', $b)
            ->where('pengirim_id', $lawan->id)
            ->whereNull('dibaca_at')
            ->update(['dibaca_at' => now()]);

        return response()->json([
            'data' => $pesan,
            'lawan' => ['id' => $lawan->id, 'nama' => $lawan->nama, 'avatar_url' => $lawan->avatar_url, 'role' => $lawan->role],
        ]);
    }

    /** POST /chat/{lawan}/pesan/{pesan}/pin — tandai/lepas tanda pesan penting. */
    public function togglePin(Request $request, User $lawan, ChatPesan $pesan)
    {
        $me = $request->user();
        $this->pastikanBolehChat($me, $lawan);
        $this->pastikanPesanMilikPercakapan($pesan, $me, $lawan);

        $pesan->update(['pinned' => ! $pesan->pinned]);

        return response()->json(['data' => $pesan]);
    }

    /**
     * POST /chat/{lawan}/hapus — hapus pesan (bisa multi-select sekaligus lewat `ids`).
     * mode 'for_me': hilang dari tampilan sendiri saja, pihak lain tetap melihat pesannya.
     * mode 'for_everyone': isi pesan hilang untuk kedua pihak — HANYA pengirim asli yang
     * boleh, dan hanya dalam 24 jam sejak dikirim (kebijakan sederhana ala aplikasi chat
     * pada umumnya, supaya tidak dipakai menghapus jejak pesan lama tanpa batas).
     */
    public function hapusPesan(Request $request, User $lawan)
    {
        $me = $request->user();
        $this->pastikanBolehChat($me, $lawan);

        $validator = Validator::make($request->all(), [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'mode' => ['required', 'in:for_me,for_everyone'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid.', 'errors' => $validator->errors()], 422);
        }

        [$a, $b] = ChatPesan::pasangan($me->id, $lawan->id);
        $sayaA = $me->id === $a;

        $pesanList = ChatPesan::where('pengguna_a_id', $a)->where('pengguna_b_id', $b)
            ->whereIn('id', $request->input('ids'))
            ->get();

        if ($request->input('mode') === 'for_everyone') {
            $bukanPengirim = $pesanList->contains(fn (ChatPesan $p) => $p->pengirim_id !== $me->id);
            abort_if($bukanPengirim, 403, 'Hanya pengirim asli yang bisa menghapus pesan untuk semua orang.');

            $kadaluarsa = $pesanList->contains(fn (ChatPesan $p) => $p->created_at->lt(now()->subDay()));
            abort_if($kadaluarsa, 422, 'Pesan lebih dari 24 jam tidak bisa dihapus untuk semua orang.');

            ChatPesan::whereIn('id', $pesanList->pluck('id'))->update(['dihapus_untuk_semua_at' => now()]);
        } else {
            $kolom = $sayaA ? 'dihapus_untuk_a_at' : 'dihapus_untuk_b_at';
            ChatPesan::whereIn('id', $pesanList->pluck('id'))->update([$kolom => now()]);
        }

        return response()->json(['message' => 'Pesan dihapus.']);
    }

    /**
     * POST /chat/{lawan}/hapus-semua — hapus SELURUH percakapan dengan satu kontak
     * sekaligus, langsung dari daftar kontak (bukan pilih pesan satu-satu). Selalu
     * mode "untuk saya" (kebijakan default hapus pesan) — pihak lain tetap melihat
     * riwayatnya. Kalau kontak itu kirim pesan baru lagi nanti, percakapan otomatis
     * muncul kembali di daftar karena ini cuma menandai pesan LAMA, bukan memblokir.
     */
    public function hapusSemuaPesan(Request $request, User $lawan)
    {
        $me = $request->user();
        $this->pastikanBolehChat($me, $lawan);

        [$a, $b] = ChatPesan::pasangan($me->id, $lawan->id);
        $sayaA = $me->id === $a;
        $kolom = $sayaA ? 'dihapus_untuk_a_at' : 'dihapus_untuk_b_at';

        ChatPesan::where('pengguna_a_id', $a)->where('pengguna_b_id', $b)
            ->whereNull($kolom)
            ->update([$kolom => now()]);

        return response()->json(['message' => 'Percakapan dihapus.']);
    }

    private function pastikanPesanMilikPercakapan(ChatPesan $pesan, User $me, User $lawan): void
    {
        [$a, $b] = ChatPesan::pasangan($me->id, $lawan->id);
        abort_unless($pesan->pengguna_a_id === $a && $pesan->pengguna_b_id === $b, 404);
    }

    public function kirim(Request $request, User $lawan)
    {
        $this->pastikanBolehChat($request->user(), $lawan);

        $validator = Validator::make($request->all(), [
            'pesan' => ['required', 'string', 'max:2000'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid.', 'errors' => $validator->errors()], 422);
        }

        [$a, $b] = ChatPesan::pasangan($request->user()->id, $lawan->id);

        $pesan = ChatPesan::create([
            'pengguna_a_id' => $a,
            'pengguna_b_id' => $b,
            'pengirim_id' => $request->user()->id,
            'pesan' => $request->input('pesan'),
        ]);

        return response()->json(['data' => $pesan], 201);
    }

    /** @return Collection<int, array{id:int,nama:?string,avatar_url:?string,peran:string}> */
    private function kontakUntukMurid(User $murid): Collection
    {
        $profile = $murid->muridProfile;
        abort_if(! $profile, 422, 'Murid belum terdaftar.');

        $guru = $profile->kelas_id
            ? PenugasanGuru::where('kelas_id', $profile->kelas_id)
                ->with(['guru:id,nama,avatar_url', 'mapel:id,nama_mapel'])
                ->get()
                ->groupBy('guru_id')
                ->map(function ($group) {
                    $pertama = $group->first();
                    $mapelNames = $group->pluck('mapel.nama_mapel')->filter()->unique()->implode(', ');

                    return [
                        'id' => $pertama->guru_id,
                        'nama' => $pertama->guru?->nama,
                        'avatar_url' => $pertama->guru?->avatar_url,
                        'peran' => 'Guru '.$mapelNames,
                    ];
                })
                ->values()
            : collect();

        $temanSekelas = $profile->kelas_id
            ? MuridProfile::where('kelas_id', $profile->kelas_id)
                ->where('user_id', '!=', $murid->id)
                ->with('user:id,nama,avatar_url')
                ->get()
                ->map(fn ($m) => [
                    'id' => $m->user_id,
                    'nama' => $m->user?->nama,
                    'avatar_url' => $m->user?->avatar_url,
                    'peran' => 'Teman sekelas',
                ])
            : collect();

        return $guru->concat($temanSekelas)->values();
    }

    /** @return Collection<int, array{id:int,nama:?string,avatar_url:?string,peran:string}> */
    private function kontakUntukGuru(User $guru): Collection
    {
        $penugasan = $guru->penugasan()->with(['kelas:id,nama_kelas', 'mapel:id,nama_mapel'])->get();

        $murid = collect();
        foreach ($penugasan as $p) {
            MuridProfile::where('kelas_id', $p->kelas_id)
                ->with('user:id,nama,avatar_url')
                ->get()
                ->each(function ($m) use ($murid, $p) {
                    $murid->put($m->user_id, [
                        'id' => $m->user_id,
                        'nama' => $m->user?->nama,
                        'avatar_url' => $m->user?->avatar_url,
                        'peran' => "Murid {$p->mapel?->nama_mapel} - {$p->kelas?->nama_kelas}",
                    ]);
                });
        }

        $guruLain = User::where('role', 'guru')
            ->where('id', '!=', $guru->id)
            ->get(['id', 'nama', 'avatar_url'])
            ->map(fn ($u) => [
                'id' => $u->id,
                'nama' => $u->nama,
                'avatar_url' => $u->avatar_url,
                'peran' => 'Sesama guru',
            ]);

        return $murid->values()->concat($guruLain)->values();
    }

    private function pastikanBolehChat(User $me, User $lawan): void
    {
        abort_if($me->id === $lawan->id, 422, 'Tidak bisa chat dengan diri sendiri.');
        abort_unless($this->bolehChat($me, $lawan), 403, 'Anda tidak bisa memulai chat dengan pengguna ini.');
    }

    private function bolehChat(User $me, User $lawan): bool
    {
        if ($me->isMurid() && $lawan->isGuru()) {
            $kelasId = $me->muridProfile?->kelas_id;

            return $kelasId && PenugasanGuru::where('guru_id', $lawan->id)->where('kelas_id', $kelasId)->exists();
        }

        if ($me->isGuru() && $lawan->isMurid()) {
            return $this->bolehChat($lawan, $me);
        }

        if ($me->isMurid() && $lawan->isMurid()) {
            $kelasMe = $me->muridProfile?->kelas_id;
            $kelasLawan = $lawan->muridProfile?->kelas_id;

            return $kelasMe !== null && $kelasMe === $kelasLawan;
        }

        if ($me->isGuru() && $lawan->isGuru()) {
            return true;
        }

        return false;
    }
}
