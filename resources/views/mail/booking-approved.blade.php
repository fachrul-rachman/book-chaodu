<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <title>Booking Anda sudah disetujui</title>
</head>
<body style="margin:0; padding:0; background:#f7f2e9; font-family:Arial, Helvetica, sans-serif; color:#241a15;">
    <div style="display:none; max-height:0; overflow:hidden; opacity:0;">
        Pembayaran telah diverifikasi. Simpan QR terlampir untuk proses gate-in.
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f7f2e9;">
        <tr>
            <td align="center" style="padding:24px 12px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:640px;">
                    <tr>
                        <td style="background:#981b1f; padding:28px 32px; border-radius:18px 18px 0 0; border-bottom:4px solid #d5a51d;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 auto;">
                                <tr>
                                    <td align="center">
                                        <img src="{{ asset('images/booking/headerlogo.png') }}" alt="Lestari Memorial Park Karawang Barat" width="180" style="display:block; max-width:180px; height:auto;">
                                    </td>
                                </tr>
                            </table>
                            <div style="font-size:28px; line-height:36px; color:#ffffff; font-weight:bold; margin-top:16px; text-align:center;">
                                Booking Chao Du Anda sudah disetujui
                            </div>
                            <div style="font-size:15px; line-height:24px; color:#f8dddd; margin-top:8px; text-align:center;">
                                Pembayaran telah diverifikasi. Berikut detail booking Anda.
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#ffffff; padding:32px; border-radius:0 0 18px 18px; box-shadow:0 4px 14px rgba(67,40,24,0.08);">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="font-size:20px; line-height:28px; font-weight:bold; padding-bottom:18px;">
                                        Detail booking
                                    </td>
                                </tr>
                            </table>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #ead8c1; border-radius:12px; overflow:hidden;">
                                <tr>
                                    <td width="42%" style="padding:14px 16px; background:#fbf7f1; font-size:14px; color:#7a6251; border-bottom:1px solid #ead8c1;">
                                        Nomor booking
                                    </td>
                                    <td style="padding:14px 16px; font-size:14px; font-weight:bold; border-bottom:1px solid #ead8c1;">
                                        {{ $bookingNumber }}
                                    </td>
                                </tr>
                                @foreach ($slotRows as $slotRow)
                                    <tr>
                                        <td width="42%" style="padding:14px 16px; background:#fbf7f1; font-size:14px; color:#7a6251; border-bottom:1px solid #ead8c1;">
                                            {{ $slotRow['label'] }}
                                        </td>
                                        <td style="padding:14px 16px; font-size:14px; font-weight:bold; border-bottom:1px solid #ead8c1;">
                                            {{ $slotRow['value'] }}
                                        </td>
                                    </tr>
                                @endforeach
                                <tr>
                                    <td width="42%" style="padding:14px 16px; background:#fbf7f1; font-size:14px; color:#7a6251; border-bottom:1px solid #ead8c1;">
                                        Nama pemesan
                                    </td>
                                    <td style="padding:14px 16px; font-size:14px; font-weight:bold; border-bottom:1px solid #ead8c1;">
                                        {{ $customerName }}
                                    </td>
                                </tr>
                                <tr>
                                    <td width="42%" style="padding:14px 16px; background:#fbf7f1; font-size:14px; color:#7a6251; border-bottom:1px solid #ead8c1;">
                                        Jumlah hadir
                                    </td>
                                    <td style="padding:14px 16px; font-size:14px; font-weight:bold; border-bottom:1px solid #ead8c1;">
                                        {{ $guestCount }} orang
                                    </td>
                                </tr>
                                <tr>
                                    <td width="42%" style="padding:14px 16px; background:#fbf7f1; font-size:14px; color:#7a6251;">
                                        Status
                                    </td>
                                    <td style="padding:14px 16px; font-size:14px; font-weight:bold; color:#2d7d46;">
                                        Pembayaran terverifikasi
                                    </td>
                                </tr>
                            </table>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="padding-top:24px; font-size:16px; line-height:24px; font-weight:bold;">
                                        Akses dokumen
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding-top:12px;">
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td style="padding-right:10px; padding-bottom:10px;">
                                                    <a href="{{ $googleDriveUrl }}" style="display:inline-block; padding:12px 18px; background:#981b1f; color:#ffffff; text-decoration:none; border-radius:999px; font-size:14px; font-weight:bold;">
                                                        Buka Google Drive
                                                    </a>
                                                </td>
                                                <td style="padding-bottom:10px;">
                                                    <a href="{{ $notionUrl }}" style="display:inline-block; padding:12px 18px; background:#f3e7d7; color:#7b211f; text-decoration:none; border-radius:999px; font-size:14px; font-weight:bold;">
                                                        Buka Notion
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:18px;">
                                <tr>
                                    <td style="background:#fff7e6; border:1px solid #efd89d; border-radius:12px; padding:16px; font-size:14px; line-height:22px; color:#5f4a35;">
                                        <strong>Simpan QR yang terlampir pada email ini.</strong><br>
                                        QR tersebut akan digunakan saat registrasi.
                                    </td>
                                </tr>
                            </table>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="padding-top:28px; border-top:1px solid #eadfd2; font-size:12px; line-height:19px; color:#8b7566;">
                                        Email ini dikirim otomatis. Mohon tidak membalas email ini.<br>
                                        &copy; {{ $year }} Chao Du. Semua hak dilindungi.
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
