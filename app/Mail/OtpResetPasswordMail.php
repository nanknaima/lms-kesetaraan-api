<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $nama, public string $otp) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Kode OTP Reset Password - LMS Kesetaraan PKBM PPI Taiwan',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.otp-reset-password',
            with: ['nama' => $this->nama, 'otp' => $this->otp],
        );
    }
}
