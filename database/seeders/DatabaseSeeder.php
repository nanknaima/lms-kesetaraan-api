<?php

namespace Database\Seeders;

use App\Models\Absensi;
use App\Models\Diskusi;
use App\Models\DiskusiReaksi;
use App\Models\Kelas;
use App\Models\Kuis;
use App\Models\Mapel;
use App\Models\Materi;
use App\Models\NilaiAkhir;
use App\Models\Pengumuman;
use App\Models\SoalKuis;
use App\Models\Tugas;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Data resmi mapel & kelas pendidikan kesetaraan (idempoten) ──
        $this->call(MapelKesetaraanSeeder::class);

        // ── Admin default ───────────────────────────────────────
        User::create([
            'nama' => 'Admin Sekolah',
            'email' => 'admin@lms-kesetaraan.sch.id',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => 'aktif',
        ]);

        // ── Mapel contoh per paket ──────────────────────────────
        $mapelList = [
            ['nama_mapel' => 'Bahasa Indonesia', 'kode' => 'BINA-A', 'paket' => 'A'],
            ['nama_mapel' => 'Matematika', 'kode' => 'MTK-B', 'paket' => 'B'],
            ['nama_mapel' => 'Bahasa Inggris', 'kode' => 'BING-C', 'paket' => 'C'],
        ];
        foreach ($mapelList as $m) {
            Mapel::create($m);
        }
        $mapelB = Mapel::where('kode', 'MTK-B')->first();

        // ── Kelas contoh ─────────────────────────────────────────
        $kelas = Kelas::create([
            'nama_kelas' => 'Paket B - Kelas 1',
            'paket' => 'B',
            'tingkat' => 1,
        ]);

        // ── Guru contoh (1 guru = 1 mapel) ──────────────────────
        $guru = User::create([
            'nama' => 'Budi Santoso',
            'email' => 'budi.guru@lms-kesetaraan.sch.id',
            'password' => Hash::make('password'),
            'role' => 'guru',
            'nip' => 'G001',
            'status' => 'aktif',
        ]);
        $guru->guruProfile()->create(['mapel_id' => $mapelB->id]);

        $kelas->update(['wali_kelas_id' => $guru->id]);

        // ── Murid contoh (NIS format [PAKET][TAHUN][URUT]) ──────
        $murid = User::create([
            'nama' => 'Rika Pramestia',
            'email' => 'rika.murid@lms-kesetaraan.sch.id',
            'password' => Hash::make('password'),
            'role' => 'murid',
            'nis' => 'B2024001',
            'status' => 'aktif',
        ]);
        $murid->muridProfile()->create([
            'paket' => 'B',
            'kelas_id' => $kelas->id,
            'status_kelulusan' => 'pending',
        ]);

        // ── Pengumuman contoh ────────────────────────────────────
        Pengumuman::create([
            'admin_id' => User::where('role', 'admin')->first()->id,
            'judul' => 'Jadwal Ujian Akhir Paket B Semester Ganjil 2026/2027',
            'konten' => 'Diberitahukan kepada seluruh murid Paket B bahwa Ujian Akhir Semester Ganjil akan dilaksanakan mulai tanggal 22–26 Desember 2026.',
            'target' => 'semua',
            'tanggal_mulai' => now(),
            'tanggal_berakhir' => now()->addDays(30),
            'pinned' => true,
            'status_publish' => 'published',
        ]);

        // ── Teman sekelas Rika (dipakai untuk demo Diskusi — komentar & reaksi
        //    perlu berasal dari user sungguhan, bukan nama palsu) ───────────
        $siti = User::create([
            'nama' => 'Siti Aminah',
            'email' => 'siti.murid@lms-kesetaraan.sch.id',
            'password' => Hash::make('password'),
            'role' => 'murid',
            'nis' => 'B2024002',
            'status' => 'aktif',
        ]);
        $siti->muridProfile()->create(['paket' => 'B', 'kelas_id' => $kelas->id, 'status_kelulusan' => 'pending']);

        $joko = User::create([
            'nama' => 'Joko Susilo',
            'email' => 'joko.murid@lms-kesetaraan.sch.id',
            'password' => Hash::make('password'),
            'role' => 'murid',
            'nis' => 'B2024003',
            'status' => 'aktif',
        ]);
        $joko->muridProfile()->create(['paket' => 'B', 'kelas_id' => $kelas->id, 'status_kelulusan' => 'pending']);

        // ── Isi mapel demo (Matematika, Paket B) supaya saat murid klik mapel
        //    ini di halaman Mata Pelajaran, semua tab (Diskusi/Materi/Tugas/
        //    Kuis) sudah terisi contoh nyata ────────────────────────────────
        Materi::create([
            'mapel_id' => $mapelB->id,
            'guru_id' => $guru->id,
            'judul' => 'Pengantar Aljabar Linear',
            'tipe' => 'pdf',
            'file_url' => null,
            'deskripsi' => 'Ringkasan materi pertemuan pertama: konsep dasar aljabar linear.',
        ]);

        Tugas::create([
            'mapel_id' => $mapelB->id,
            'guru_id' => $guru->id,
            'kelas_id' => $kelas->id,
            'judul' => 'Tugas Akhir Semester - Aljabar Linear',
            'instruksi' => 'Kerjakan seluruh soal pada lampiran dan kumpulkan sebelum batas waktu.',
            'lampiran_url' => null,
            'deadline' => now()->addDays(10),
        ]);

        $kuis = Kuis::create([
            'mapel_id' => $mapelB->id,
            'guru_id' => $guru->id,
            'kelas_id' => $kelas->id,
            'judul' => 'Kuis Aljabar Linear - Sesi 1',
            'durasi_menit' => 20,
            'total_soal' => 2,
            'random_soal' => false,
            'mulai' => now()->subDay(),
            'selesai' => now()->addDays(14),
        ]);
        SoalKuis::create([
            'kuis_id' => $kuis->id,
            'pertanyaan' => 'Berapa nilai x jika 2x + 3 = 7?',
            'tipe' => 'pilihan_ganda',
            'opsi' => ['1', '2', '3', '4'],
            'jawaban_benar' => '2',
            'poin' => 50,
            'urutan' => 1,
        ]);
        SoalKuis::create([
            'kuis_id' => $kuis->id,
            'pertanyaan' => 'Matriks identitas berordo 2x2 memiliki determinan 1.',
            'tipe' => 'benar_salah',
            'opsi' => null,
            'jawaban_benar' => 'Benar',
            'poin' => 50,
            'urutan' => 2,
        ]);

        // ── Absensi demo untuk Rika (dipakai kartu "Presensi/Kehadiran") ────
        foreach ([-21, -14, -7] as $hariLalu) {
            Absensi::create([
                'mapel_id' => $mapelB->id,
                'murid_id' => $murid->id,
                'tanggal' => now()->addDays($hariLalu),
                'status' => 'hadir',
            ]);
        }
        Absensi::create([
            'mapel_id' => $mapelB->id,
            'murid_id' => $murid->id,
            'tanggal' => now()->subDays(3),
            'status' => 'izin',
            'keterangan' => 'Sakit ringan.',
        ]);

        // ── Nilai Akhir demo yang sudah dirilis (dipakai menu Nilai Akhir & Rapor) ─
        NilaiAkhir::create([
            'murid_id' => $murid->id,
            'mapel_id' => $mapelB->id,
            'nilai_tugas' => 88,
            'nilai_kuis' => 90,
            'nilai_absensi' => 95,
            'nilai_ujian' => 85,
            'nilai_akhir' => 88.9,
            'status_kelulusan' => 'lulus',
            'dirilis_ke_murid' => true,
            'verified_by_admin' => true,
        ]);

        // ── Diskusi demo: guru mengumumkan tugas, murid bereaksi & berkomentar ─
        $diskusiUtama = Diskusi::create([
            'mapel_id' => $mapelB->id,
            'user_id' => $guru->id,
            'pesan' => 'Menambahkan tugas "Tugas Akhir Semester - Aljabar Linear". Batas pengumpulan '
                .now()->addDays(10)->format('d M Y H:i').'. Jangan lupa dikerjakan ya!',
        ]);
        DiskusiReaksi::create(['diskusi_id' => $diskusiUtama->id, 'user_id' => $murid->id, 'emoji' => '👍']);
        DiskusiReaksi::create(['diskusi_id' => $diskusiUtama->id, 'user_id' => $siti->id, 'emoji' => '❤️']);
        DiskusiReaksi::create(['diskusi_id' => $diskusiUtama->id, 'user_id' => $joko->id, 'emoji' => '👍']);

        $komentar1 = Diskusi::create(['mapel_id' => $mapelB->id, 'user_id' => $siti->id, 'pesan' => 'Terima kasih Pak']);
        Diskusi::create(['mapel_id' => $mapelB->id, 'user_id' => $joko->id, 'pesan' => 'Siap Pak, dikerjakan sekarang.']);
        DiskusiReaksi::create(['diskusi_id' => $komentar1->id, 'user_id' => $murid->id, 'emoji' => '🙏']);

        $this->command->info('Seed selesai. Akun demo:');
        $this->command->info('  Admin : admin@lms-kesetaraan.sch.id / password');
        $this->command->info('  Guru  : budi.guru@lms-kesetaraan.sch.id / password');
        $this->command->info('  Murid : B2024001 (atau rika.murid@lms-kesetaraan.sch.id) / password');
        $this->command->info('  Murid lain (utk demo diskusi): B2024002 & B2024003 / password');
    }
}
