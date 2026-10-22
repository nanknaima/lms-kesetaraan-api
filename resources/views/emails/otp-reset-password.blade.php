<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kode OTP Reset Password</title>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9;padding:32px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="480" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border-radius:12px;overflow:hidden;">
                    <tr>
                        <td style="background-color:#0f766e;padding:24px 32px;">
                            <span style="color:#ffffff;font-size:18px;font-weight:bold;">LMS Kesetaraan — PKBM PPI Taiwan</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <p style="font-size:14px;color:#334155;margin:0 0 8px;">Halo {{ $nama }},</p>
                            <p style="font-size:14px;color:#334155;margin:0 0 24px;">
                                Kami menerima permintaan untuk mengatur ulang password akun Anda. Gunakan kode OTP
                                berikut untuk melanjutkan:
                            </p>
                            <div style="text-align:center;margin:0 0 24px;">
                                <span style="display:inline-block;background-color:#f0fdfa;color:#0f766e;font-size:32px;font-weight:bold;letter-spacing:8px;padding:16px 24px;border-radius:8px;">
                                    {{ $otp }}
                                </span>
                            </div>
                            <p style="font-size:13px;color:#64748b;margin:0 0 8px;">
                                Kode ini berlaku selama <strong>10 menit</strong>. Jangan bagikan kode ini kepada siapa pun,
                                termasuk pihak yang mengaku dari admin sekolah.
                            </p>
                            <p style="font-size:13px;color:#64748b;margin:0;">
                                Jika Anda tidak merasa meminta ini, abaikan saja email ini — password Anda tidak akan berubah.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 32px;background-color:#f8fafc;">
                            <p style="font-size:11px;color:#94a3b8;margin:0;">
                                Email otomatis dari sistem LMS Kesetaraan PKBM PPI Taiwan. Mohon tidak membalas email ini.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
