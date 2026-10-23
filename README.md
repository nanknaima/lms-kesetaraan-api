# Backend — LMS Kesetaraan API (Laravel)

## Status scaffold ini

Kode di folder ini adalah **scaffold Laravel yang lengkap secara struktur** (migrations, models,
middleware RBAC, service, jobs, dan seluruh controller REST sesuai `docs/api-endpoints.md`),
ditulis tangan karena sandbox tempat proyek ini dibuat **tidak punya akses ke Packagist**
(hanya npm registry yang terbuka), sehingga `composer install` tidak bisa dijalankan di sini.

Semua ~70 file PHP sudah lolos `php -l` (cek sintaks) tanpa error. Yang **belum** bisa diverifikasi
di sandbox ini: `composer install` (unduh vendor Laravel/Sanctum/dll), `php artisan migrate`,
dan `php artisan route:list`. Jalankan langkah di bawah ini di komputer/server Anda yang
punya akses internet normal.

## Setup lokal

```bash
composer install
cp .env.example .env
php artisan key:generate

# Sanctum & permission perlu publish config bawaan paket:
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"

# Sesuaikan .env: DB_*, REDIS_*, AWS_* (atau ganti FILESYSTEM_DISK=local untuk dev), MAIL_*

php artisan migrate --seed   # akun admin default: admin@lms-kesetaraan.sch.id / password
php artisan storage:link     # agar file di storage/app/public bisa diakses publik

php artisan serve            # http://https://lms-kesetaraan-api.vercel.app/api
php artisan queue:work       # worker untuk reminder pertemuan, email kredensial, dsb.
php artisan schedule:work    # scheduler (cek pertemuan H-1/H-15m tiap menit)
```

## Yang sudah diimplementasikan penuh (bukan sekadar stub)

- **Auth**: login (email/NIP/NIS) + access/refresh token (Sanctum), RBAC middleware `role:`.
- **Fitur contoh end-to-end**: Tugas — `Guru\TugasController` (create/update/delete, lihat
  submission, download hasil upload murid, grading) + `Murid\TugasSubmitController` (submit)
  + `Guru\ExportController` (export Excel via `maatwebsite/excel` & PDF via `barryvdh/laravel-dompdf`,
  lihat `resources/views/exports/rekap-tabel.blade.php` untuk template kop sekolah).
- **Pengumuman slide show**: `PengumumanController` (CRUD admin + endpoint `/pengumuman/slideshow`
  yang sudah memfilter status publish, periode tayang, target paket/kelas, dan urutan pinned).
- **Pertemuan online**: `Guru\PertemuanOnlineController` (validasi format link Zoom/Meet via
  `MeetingLinkValidatorService`) + `Murid\PertemuanJoinController` (validasi murid terdaftar
  di kelas + tombol join aktif 10 menit sebelum jadwal) + `CheckUpcomingMeetings` command
  (reminder H-1 & H-15 menit, dijadwalkan di `routes/console.php`).
- **Pendaftaran murid baru**: `PendaftaranPublicController` (form publik) +
  `Admin\PendaftaranController::approve()` (generate NIS otomatis via `NisGeneratorService`,
  format `[PAKET][TAHUN][URUT]`, kirim email kredensial via queue job).
- **Nilai akhir & kelulusan**: `NilaiAkhirCalculatorService` (formula bobot dari
  `BobotNilaiConfig`, bisa override per mapel) + alur guru rekomendasi → admin verifikasi
  (`Admin\KelulusanController`).

## Controller lain (Kelas/Mapel/Guru/Murid admin CRUD, Kuis, Absensi, Materi, Notifikasi)

Sudah ditulis lengkap mengikuti pola yang sama (validasi, scoping per role, response JSON
konsisten) — lihat masing-masing folder `app/Http/Controllers/Api/{Admin,Guru,Murid}`.
Beberapa method (mis. `Admin\MuridController::store`, `Admin\LaporanController` bagian
lanjutan) sengaja diberi komentar "pola sama seperti X" agar ringkas — tinggal ikuti pola
controller yang sudah lengkap di sebelahnya bila ingin melengkapinya lebih detail.

## Catatan penting sebelum production

1. Ganti `FILESYSTEM_DISK=s3` di `.env` dan isi kredensial S3/Cloudinary.
2. Set `SANCTUM_STATEFUL_DOMAINS` ke domain web production Anda.
3. Aktifkan HTTPS + `SESSION_SECURE_COOKIE=true`.
4. Tambahkan Form Request class terpisah (`app/Http/Requests`) untuk validasi yang lebih
   rapi jika controller mulai terasa gemuk — saat ini validasi inline di controller agar
   mudah dibaca sebagai referensi awal.
5. Tambahkan `AuditLogObserver` (lihat `app/Observers`, saat ini masih kosong) ke model
   penting (`NilaiAkhir`, `User`) untuk memenuhi aturan bisnis #15 (audit trail penuh).

## Deploy API ke Vercel

Hubungkan repo backend ke Vercel dan gunakan root directory repo backend. Isi environment
variables untuk Production: `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY`,
`APP_URL=https://lms-kesetaraan-api.vercel.app`, serta `DB_CONNECTION=mysql` dan
`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` dari penyedia database.
Jangan gunakan `localhost` sebagai host database terkelola.

Koneksi MySQL/TiDB menggunakan TLS dengan verifikasi sertifikat. Secara default API memakai
sertifikat ISRG Root X1 di `config/cancert.pem`; jika penyedia database memberi CA lain,
simpan CA itu pada repo backend privat dan atur `DB_SSL_CA` ke path file tersebut.
Set `SANCTUM_STATEFUL_DOMAINS` ke domain web tanpa `https://`, dan izinkan origin web HTTPS
di `config/cors.php`. Jalankan migrasi production satu kali sebelum login, lalu redeploy API
dan frontend setelah mengubah kode atau environment variables. Jangan pernah commit `.env`
atau nilai kredensial database.

### Menyiapkan akun demo

Gunakan hanya pada database TiDB yang terisolasi dan berisi data palsu. Seeder berikut
hanya membuat/memperbarui tiga email demo yang ditentukan, mereset token milik ketiga akun
tersebut, dan membatalkan transaksi jika NIP/NIS target dipakai akun lain atau nama database
tidak cocok. Seeder tidak menghapus akun atau data lain.

Di salinan lokal privat `backend/.env`, isi `DEMO_DATA_ONLY=true`,
`ALLOW_DEMO_ACCOUNT_RESET=true`, `DEMO_DATABASE_NAME` dengan nama database TiDB demo yang
tepat, dan `DEMO_ACCOUNT_PASSWORD` dengan password yang disetujui untuk demo. Jangan
menaruh password di Git, chat, atau environment frontend. Pastikan `DB_*` menunjuk ke
database demo yang sama, lalu jalankan dari folder backend:

```bash
php artisan db:seed --class="Database\\Seeders\\DemoAccountsSeeder" --force
```

Akun murid dapat masuk menggunakan NIS `B2024001`; guru menggunakan NIP `G001`; admin
menggunakan email `admin@lms-kesetaraan.sch.id`. Ketiganya memakai nilai privat
`DEMO_ACCOUNT_PASSWORD`. Akun admin demo memiliki akses super, sehingga setelah sesi demo
password harus segera dirotasi atau akun dinonaktifkan.
