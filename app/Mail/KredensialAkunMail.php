<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Email kredensial akun baru (NIS/email + password awal) — dikirim saat pendaftaran disetujui atau admin buat akun murid langsung. */
class KredensialAkunMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $passwordAwal) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Akun LMS Kesetaraan PKBM PPI Taiwan Anda Sudah Aktif',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.kredensial',
            with: [
                'nama' => $this->user->nama,
                'identifier' => $this->user->nis ?? $this->user->email,
                'email' => $this->user->email,
                'passwordAwal' => $this->passwordAwal,
                'loginUrl' => rtrim(config('app.frontend_url'), '/').'/login',
            ],
        );
    }
}
