<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Kuis extends Model
{
    protected $table = 'kuis';
    protected $fillable = ['mapel_id', 'guru_id', 'kelas_id', 'judul', 'durasi_menit', 'total_soal', 'random_soal', 'mulai', 'selesai'];
    protected $casts = ['random_soal' => 'boolean', 'mulai' => 'datetime', 'selesai' => 'datetime'];

    /**
     * Mutator eksplisit "mulai"/"selesai" — WAJIB ada supaya kolom DATETIME
     * naif (tanpa info zona) tetap benar dipasangkan dengan device manapun
     * yang mengirimnya. Tanpa ini, Eloquent::fromDateTime() (dipanggil
     * setAttribute() untuk cast 'datetime') memformat value HANYA dengan
     * Carbon::format(), yang menuliskan digit jam apa adanya sesuai zona si
     * instance itu sendiri TANPA mengonversi ke config('app.timezone') dulu —
     * jadi kalau frontend mengirim string berzona eksplisit (mis. ISO "...Z"
     * hasil konversi dari timezone device pengguna), digit jam UTC-nya
     * tersimpan mentah dan nanti dibaca ulang seolah-olah sudah WIB (offset
     * salah sebesar selisih zona, ditemukan lewat pengujian nyata: kirim
     * "15:08:00Z" tersimpan jadi "15:08:00" WIB, bukan dikonversi ke "22:08:00"
     * WIB). `Carbon::parse($value)->setTimezone(...)` di bawah ini
     * mengonversi (bukan sekadar re-label) ke config('app.timezone') SEBELUM
     * diserahkan ke cast bawaan — input naif (tanpa zona, seperti selama ini
     * dari form web) tetap diperlakukan identik seperti sebelumnya karena
     * Carbon::parse() tanpa info zona sudah otomatis pakai
     * date_default_timezone_get() (= config('app.timezone')), jadi
     * setTimezone() ke zona yang sama itu adalah no-op.
     */
    protected function mulai(): Attribute
    {
        return Attribute::make(
            set: fn (mixed $value) => $value ? Carbon::parse($value)->setTimezone(config('app.timezone')) : $value,
        );
    }

    protected function selesai(): Attribute
    {
        return Attribute::make(
            set: fn (mixed $value) => $value ? Carbon::parse($value)->setTimezone(config('app.timezone')) : $value,
        );
    }

    public function soal()
    {
        return $this->hasMany(SoalKuis::class);
    }

    public function mapel()
    {
        return $this->belongsTo(Mapel::class);
    }

    public function kelas()
    {
        return $this->belongsTo(Kelas::class);
    }
}
