@php
    $logoPath = public_path('images/booking/headerlogo.png');
    $logoData = null;

    if (is_readable($logoPath)) {
        $logoContents = file_get_contents($logoPath);
        $logoData = is_string($logoContents)
            ? 'data:image/png;base64,'.base64_encode($logoContents)
            : null;
    }
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#8a2d1f">
    <title>Kami segera kembali</title>
    <style>
        * { box-sizing: border-box; }

        html { color-scheme: light; }

        body {
            min-height: 100vh;
            margin: 0;
            display: grid;
            place-items: center;
            padding: 24px;
            color: #1f2937;
            background:
                radial-gradient(circle at top, rgba(238, 216, 201, .9), transparent 42%),
                linear-gradient(180deg, #f8f3eb 0%, #f2eadf 100%);
            font-family: Arial, Helvetica, sans-serif;
        }

        main {
            width: min(100%, 620px);
            overflow: hidden;
            border: 1px solid #d8c9b5;
            border-radius: 24px;
            background: rgba(255, 250, 242, .96);
            box-shadow: 0 24px 60px rgba(74, 45, 32, .12);
        }

        .accent { height: 7px; background: linear-gradient(90deg, #8a2d1f, #d5a51d); }

        .content { padding: 40px; text-align: center; }

        .logo {
            display: block;
            width: min(100%, 280px);
            height: auto;
            margin: 0 auto 28px;
        }

        .eyebrow {
            margin: 0 0 12px;
            color: #8a2d1f;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
        }

        h1 {
            margin: 0;
            color: #241a15;
            font-size: clamp(30px, 7vw, 44px);
            line-height: 1.12;
        }

        .message {
            max-width: 480px;
            margin: 18px auto 0;
            color: #5f5148;
            font-size: 17px;
            line-height: 1.7;
        }

        .contact {
            margin-top: 30px;
            padding: 20px;
            border: 1px solid #ead8c1;
            border-radius: 18px;
            background: #fff;
        }

        .contact p {
            margin: 0 0 12px;
            color: #5f5148;
            font-size: 14px;
            line-height: 1.5;
        }

        .button {
            display: inline-flex;
            min-height: 48px;
            align-items: center;
            justify-content: center;
            padding: 12px 22px;
            border-radius: 999px;
            color: #fff;
            background: #8a2d1f;
            font-size: 16px;
            font-weight: 700;
            text-decoration: none;
        }

        .button:focus-visible {
            outline: 3px solid #d5a51d;
            outline-offset: 4px;
        }

        .number { display: block; margin-top: 9px; color: #735e50; font-size: 13px; }

        @media (max-width: 520px) {
            body { padding: 16px; }
            .content { padding: 32px 22px; }
            .message { font-size: 16px; }
            .button { width: 100%; }
        }
    </style>
</head>
<body>
    <main>
        <div class="accent"></div>
        <div class="content">
            @if ($logoData)
                <img class="logo" src="{{ $logoData }}" alt="Lestari Memorial Park Karawang Barat">
            @endif

            <p class="eyebrow">Pemeliharaan sistem</p>
            <h1>Kami segera kembali</h1>
            <p class="message">
                Sistem sedang menjalani pemeliharaan singkat agar layanan tetap aman dan lancar.
                Silakan coba kembali beberapa saat lagi.
            </p>

            <div class="contact">
                <p>Jika membutuhkan bantuan, silakan hubungi tim kami.</p>
                <a class="button" href="https://wa.me/6282163332227">Hubungi kami</a>
                <span class="number">+62 821-6333-2227</span>
            </div>
        </div>
    </main>
</body>
</html>
