<?php

namespace App\Services;

class MeetingLinkValidatorService
{
    /**
     * Validasi format link Zoom / Google Meet (aturan bisnis: "validasi format link").
     * Mengembalikan platform yang terdeteksi, atau null jika tidak cocok pola manapun
     * (untuk platform 'lainnya' tetap wajib berupa URL valid, dicek terpisah di FormRequest).
     */
    public function detectPlatform(string $link): ?string
    {
        $link = trim($link);

        if (preg_match('#^https://([a-z0-9-]+\.)?zoom\.us/(j|my|w)/[A-Za-z0-9?=&_-]+#i', $link)) {
            return 'zoom';
        }

        if (preg_match('#^https://meet\.google\.com/[a-z0-9-]{3,}(\?.*)?$#i', $link)) {
            return 'google_meet';
        }

        return null; // biarkan tervalidasi sebagai 'lainnya' selama lolos rule `url`
    }

    public function isValidForPlatform(string $link, string $platform): bool
    {
        $detected = $this->detectPlatform($link);

        if ($platform === 'lainnya') {
            return filter_var($link, FILTER_VALIDATE_URL) !== false;
        }

        return $detected === $platform;
    }
}
