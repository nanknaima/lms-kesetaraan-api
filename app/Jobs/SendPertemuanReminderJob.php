<?php

namespace App\Jobs;

use App\Models\MuridProfile;
use App\Models\Notifikasi;
use App\Models\PertemuanOnline;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendPertemuanReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public PertemuanOnline $pertemuan) {}

    public function handle(): void
    {
        $murid = MuridProfile::where('kelas_id', $this->pertemuan->kelas_id)->with('user')->get();

        foreach ($murid as $m) {
            Notifikasi::create([
                'user_id' => $m->user_id,
                'tipe' => 'pertemuan',
                'judul' => "Pertemuan online \"{$this->pertemuan->judul}\" segera dimulai",
                'pesan' => "Jadwal: {$this->pertemuan->tanggal->format('d/m/Y')} {$this->pertemuan->jam_mulai}",
                'link' => "/murid/pertemuan-online/{$this->pertemuan->id}",
                'created_at' => now(),
            ]);

            // TODO: kirim email (Mail::to($m->user->email)->queue(new PertemuanReminderMail($this->pertemuan)))
            // TODO: kirim push notification via FCM (lihat App\Services\FcmService — belum diimplementasi)
        }

        // Guru juga menerima reminder H-1 & 15 menit.
        Notifikasi::create([
            'user_id' => $this->pertemuan->guru_id,
            'tipe' => 'pertemuan',
            'judul' => "Pertemuan online \"{$this->pertemuan->judul}\" segera dimulai",
            'pesan' => 'Pastikan link meeting sudah aktif.',
            'link' => "/guru/pertemuan-online/{$this->pertemuan->id}",
            'created_at' => now(),
        ]);
    }
}
