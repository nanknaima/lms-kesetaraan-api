<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Akun LMS Anda Sudah Aktif</title>
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
                            <p style="font-size:14px;color:#334155;margin:0 0 8px;">Halo <?php echo e($nama); ?>,</p>
                            <p style="font-size:14px;color:#334155;margin:0 0 24px;">
                                Akun Anda di LMS Kesetaraan PKBM PPI Taiwan sudah aktif. Gunakan data berikut untuk masuk
                                pertama kali:
                            </p>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f0fdfa;border-radius:8px;margin:0 0 24px;">
                                <tr>
                                    <td style="padding:16px 20px;">
                                        <p style="font-size:12px;color:#64748b;margin:0 0 2px;">Email / NIS</p>
                                        <p style="font-size:16px;font-weight:bold;color:#0f766e;margin:0 0 12px;"><?php echo e($identifier); ?></p>
                                        <p style="font-size:12px;color:#64748b;margin:0 0 2px;">Password awal</p>
                                        <p style="font-size:20px;font-weight:bold;letter-spacing:2px;color:#0f766e;margin:0;"><?php echo e($passwordAwal); ?></p>
                                    </td>
                                </tr>
                            </table>
                            <div style="text-align:center;margin:0 0 24px;">
                                <a href="<?php echo e($loginUrl); ?>" style="display:inline-block;background-color:#0f766e;color:#ffffff;font-size:14px;font-weight:bold;padding:12px 28px;border-radius:8px;text-decoration:none;">
                                    Masuk ke LMS
                                </a>
                            </div>
                            <p style="font-size:13px;color:#64748b;margin:0;">
                                Demi keamanan, segera ganti password Anda setelah berhasil masuk lewat menu Profil.
                                Jangan bagikan password ini kepada siapa pun.
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
<?php /**PATH C:\Asia Cyber University\magang\lms-pkbm-ppi-taiwan\backend\resources\views/emails/kredensial.blade.php ENDPATH**/ ?>