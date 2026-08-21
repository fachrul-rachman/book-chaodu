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
        th, td { border: 1px solid #cbd5e1; padding: 4px; vertical-align: top; word-wrap: break-word; }
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
                <th>Kode booking</th>
                <th>Nama customer</th>
                <th>Nomor telepon</th>
                <th>Nama agent</th>
                <th>Tanggal disetujui</th>
                <th>Paket</th>
                <th>Nomor meja</th>
                <th>Nomor hio</th>
            </tr>
        </thead>
        <tbody>
            @foreach (($payload['rows'] ?? []) as $row)
                <tr>
                    <td>{{ $row['booking_number'] }}</td>
                    <td>{{ $row['customer_name'] }}</td>
                    <td>{{ $row['customer_phone'] ?: '-' }}</td>
                    <td>{{ $row['agent_name'] ?: '-' }}</td>
                    <td>{{ $row['approval_date'] ?: '-' }}</td>
                    <td>{{ $row['package_name'] }}</td>
                    <td>{{ $row['table_number'] ?: '-' }}</td>
                    <td>{{ $row['incense_number'] ?: '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
