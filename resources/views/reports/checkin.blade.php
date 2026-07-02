<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { size: A4 landscape; margin: 12px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111827; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        p { margin: 0 0 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; table-layout: fixed; }
        th, td { border: 1px solid #cbd5e1; padding: 4px; vertical-align: top; word-wrap: break-word; }
        th { background: #f8fafc; text-align: left; }
        .meta { margin-bottom: 12px; }
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
                <th>Nomor booking</th>
                <th>Nama customer</th>
                <th>Nomor telepon</th>
                <th>Paket</th>
                <th>Jumlah hadir</th>
                <th>Vegetarian</th>
                <th>Non vegetarian</th>
                <th>Nomor meja</th>
                <th>Nomor hio</th>
                <th>Nama agent</th>
                <th>Check-in manual</th>
                <th>Catatan</th>
            </tr>
        </thead>
        <tbody>
            @foreach (($payload['rows'] ?? []) as $row)
                <tr>
                    <td>{{ $row['booking_number'] }}</td>
                    <td>{{ $row['customer_name'] }}</td>
                    <td>{{ $row['customer_phone'] }}</td>
                    <td>{{ $row['package_name'] }}</td>
                    <td>{{ $row['attendee_count'] }}</td>
                    <td>{{ $row['vegetarian_quantity'] }}</td>
                    <td>{{ $row['non_vegetarian_quantity'] }}</td>
                    <td>{{ $row['table_number'] ?: '-' }}</td>
                    <td>{{ $row['incense_number'] ?: '-' }}</td>
                    <td>{{ $row['agent_name'] ?: '-' }}</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
