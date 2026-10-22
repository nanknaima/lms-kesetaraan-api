<?php

namespace Database\Seeders;

use App\Models\Kelas;
use App\Models\Mapel;
use Illuminate\Database\Seeder;

/**
 * Data resmi mata pelajaran & kelas pendidikan kesetaraan (Paket A/B/C) —
 * dibutuhkan supaya halaman "Kelas & Mapel Saya" (model Penugasan Mengajar,
 * lihat Tahap 1-6 sebelumnya) punya kombinasi kelas x mapel yang lengkap per
 * paket, bukan cuma sisa migrasi otomatis dari guru lama (yang sebelumnya
 * cuma menghasilkan 1 mapel + 1 kelas per paket, seadanya).
 *
 * IDEMPOTEN — aman dijalankan berkali-kali:
 *   - Mapel dicari lewat (nama_mapel, paket): kalau sudah ada, dilewati.
 *   - Kelas dicari lewat (paket, tingkat) — BUKAN nama_kelas — karena data
 *     lama (mis. "Paket B - Kelas 1", tingkat=1) memakai format nama yang
 *     beda dari seeder ini ("Kelas 1"); mencocokkan lewat (paket, tingkat)
 *     memastikan kelas yang sudah dipakai guru aktif tidak pernah
 *     terduplikasi walau namanya berbeda format.
 *
 * Mapel peminatan/keterampilan Paket C sengaja TIDAK diseed di sini (beda
 * per jurusan) — admin menambahkannya manual lewat halaman Kelas & Mapel.
 */
class MapelKesetaraanSeeder extends Seeder
{
    /** @var array<string, string[]> */
    private const MAPEL_PER_PAKET = [
        'A' => ['Pendidikan Agama', 'PPKn', 'Bahasa Indonesia', 'Matematika', 'IPA', 'IPS', 'Seni Budaya', 'PJOK'],
        'B' => ['Pendidikan Agama', 'PPKn', 'Bahasa Indonesia', 'Matematika', 'IPA', 'IPS', 'Bahasa Inggris', 'Seni Budaya', 'PJOK', 'Prakarya'],
        'C' => ['Pendidikan Agama', 'PPKn', 'Bahasa Indonesia', 'Matematika', 'Bahasa Inggris', 'Sejarah'],
    ];

    /** Singkatan kode mapel — dipakai bareng paket supaya `kode` (unique) tidak pernah bentrok. */
    private const KODE_ABBREV = [
        'Pendidikan Agama' => 'AGM',
        'PPKn' => 'PPKN',
        'Bahasa Indonesia' => 'BINA',
        'Matematika' => 'MTK',
        'IPA' => 'IPA',
        'IPS' => 'IPS',
        'Seni Budaya' => 'SBD',
        'PJOK' => 'PJOK',
        'Bahasa Inggris' => 'BING',
        'Prakarya' => 'PKY',
        'Sejarah' => 'SJR',
    ];

    /** Jumlah kelas per paket sesuai jenjang: Paket A (setara SD) 6 kelas, Paket B & C (setara SMP/SMA) 3 kelas. */
    private const JUMLAH_KELAS_PER_PAKET = [
        'A' => 6,
        'B' => 3,
        'C' => 3,
    ];

    public function run(): void
    {
        $mapelDibuat = 0;
        foreach (self::MAPEL_PER_PAKET as $paket => $daftarMapel) {
            foreach ($daftarMapel as $namaMapel) {
                $mapel = Mapel::firstOrCreate(
                    ['nama_mapel' => $namaMapel, 'paket' => $paket],
                    ['kode' => self::KODE_ABBREV[$namaMapel].'-'.$paket],
                );
                if ($mapel->wasRecentlyCreated) {
                    $mapelDibuat++;
                }
            }
        }

        $kelasDibuat = 0;
        foreach (self::JUMLAH_KELAS_PER_PAKET as $paket => $jumlah) {
            for ($tingkat = 1; $tingkat <= $jumlah; $tingkat++) {
                $kelas = Kelas::firstOrCreate(
                    ['paket' => $paket, 'tingkat' => (string) $tingkat],
                    ['nama_kelas' => "Kelas {$tingkat}"],
                );
                if ($kelas->wasRecentlyCreated) {
                    $kelasDibuat++;
                }
            }
        }

        $this->command->info("MapelKesetaraanSeeder selesai: {$mapelDibuat} mapel baru, {$kelasDibuat} kelas baru dibuat (yang sudah ada dilewati).");
    }
}
