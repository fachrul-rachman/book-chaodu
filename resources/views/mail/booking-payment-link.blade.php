<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lanjutkan Pembayaran Booking</title>
</head>
<body style="margin:0;padding:0;background:#f6efe7;font-family:Arial,sans-serif;color:#2c1810;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f6efe7;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border-radius:20px;overflow:hidden;">
                    <tr>
                        <td style="background:#8b1a1a;padding:28px 32px;color:#ffffff;">
                            <div style="font-size:14px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;">Chao Du Booking</div>
                            <div style="margin-top:8px;font-size:28px;font-weight:700;line-height:1.3;">Lanjutkan pembayaran booking Anda</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <p style="margin:0 0 16px;font-size:16px;line-height:1.7;">
                                Halo {{ $booking->customer_name }}, data booking Anda sudah kami terima.
                                Silakan lanjutkan pembayaran melalui tombol di bawah ini.
                            </p>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:24px 0;background:#fdf8f0;border-radius:16px;">
                                <tr>
                                    <td style="padding:20px 22px;">
                                        <p style="margin:0 0 8px;font-size:13px;color:#6b4a3d;">Nomor booking</p>
                                        <p style="margin:0 0 16px;font-size:24px;font-weight:700;">{{ $booking->booking_number }}</p>
                                        <p style="margin:0 0 8px;font-size:13px;color:#6b4a3d;">Paket</p>
                                        <p style="margin:0 0 16px;font-size:16px;font-weight:600;">{{ $booking->package_name_snapshot }}</p>
                                        <p style="margin:0 0 8px;font-size:13px;color:#6b4a3d;">Batas waktu pembayaran</p>
                                        <p style="margin:0;font-size:16px;font-weight:600;">{{ $expiresAt }}</p>
                                    </td>
                                </tr>
                            </table>

                            <table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 auto 24px;">
                                <tr>
                                    <td align="center" style="border-radius:999px;background:#8b1a1a;">
                                        <a href="{{ $paymentUrl }}" style="display:inline-block;padding:14px 28px;color:#ffffff;text-decoration:none;font-size:16px;font-weight:700;">
                                            Buka halaman pembayaran
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0;font-size:14px;line-height:1.7;color:#6b4a3d;">
                                Jika link sudah lewat waktu, booking akan hangus dan Anda perlu melakukan booking ulang.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
