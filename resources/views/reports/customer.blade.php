<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { size: A4 landscape; margin: 10px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #111827; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        p { margin: 0 0 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; table-layout: fixed; }
        th, td { border: 1px solid #cbd5e1; padding: 3px; vertical-align: top; word-wrap: break-word; }
        th { background: #f8fafc; text-align: left; }
        .meta { margin-bottom: 10px; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <p>{{ $app_name }}</p>
    <p>Dibuat: {{ $generated_at }}</p>
    <div class="meta">
        @foreach ($filters as $line)
            <p>{{ $line }}</p>
        @endforeach
    </div>

    <table>
        <thead>
            <tr>
                <th>Nomor Booking</th>
                <th>Nomor Meja/Hio</th>
                <th>Tanggal Booking</th>
                <th>Nama Customer</th>
                <th>Nomor Telepon</th>
                <th>Email</th>
                <th>Paket</th>
                <th>Kertas Doa 1</th>
                <th>Kertas Doa 2</th>
                <th>Kertas Hio</th>
            </tr>
        </thead>
        <tbody>
            @foreach (($payload['rows'] ?? []) as $row)
                <tr>
                    <td>{{ $row['booking_number'] }}</td>
                    <td>{{ $row['slot_number'] ?: '-' }}</td>
                    <td>{{ $row['booking_date'] ?: '-' }}</td>
                    <td>{{ $row['customer_name'] }}</td>
                    <td>{{ $row['customer_phone'] }}</td>
                    <td>{{ $row['customer_email'] }}</td>
                    <td>{{ $row['package_name'] }}</td>
                    <td>{{ $row['prayer_paper_1']['name'] ?: '-' }}</td>
                    <td>{{ $row['prayer_paper_2']['name'] ?: '-' }}</td>
                    <td>{{ $row['incense_paper']['name'] ?: '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
