<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111827; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        h2 { font-size: 15px; margin: 18px 0 8px; }
        p { margin: 0 0 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #cbd5e1; padding: 6px; vertical-align: top; }
        th { background: #f8fafc; text-align: left; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <p>{{ $app_name }}</p>
    <p>Dibuat: {{ $generated_at }}</p>
    @foreach ($filters as $line)
        <p>{{ $line }}</p>
    @endforeach

    <h2>Ringkasan</h2>
    <p>Total booking approved: {{ $payload['summary']['total_bookings'] ?? 0 }}</p>
    <p>Total uang masuk: Rp {{ number_format((float) ($payload['summary']['total_revenue'] ?? 0), 0, ',', '.') }}</p>

    <table>
        <thead>
            <tr>
                <th>Paket</th>
                <th>Jumlah booking</th>
                <th>Total uang masuk</th>
            </tr>
        </thead>
        <tbody>
            @foreach (($payload['summary']['by_package'] ?? []) as $row)
                <tr>
                    <td>{{ $row['package_name'] }}</td>
                    <td>{{ $row['booking_count'] }}</td>
                    <td>Rp {{ number_format((float) $row['total_revenue'], 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Detail</h2>
    <table>
        <thead>
            <tr>
                <th>Nomor booking</th>
                <th>Tanggal booking</th>
                <th>Tanggal setuju</th>
                <th>Nama customer</th>
                <th>Paket</th>
                <th>Nominal</th>
                <th>Nomor VA</th>
                <th>Sumber</th>
                <th>Agent</th>
            </tr>
        </thead>
        <tbody>
            @foreach (($payload['rows'] ?? []) as $row)
                <tr>
                    <td>{{ $row['booking_number'] }}</td>
                    <td>{{ $row['booking_date'] ?: '-' }}</td>
                    <td>{{ $row['approval_date'] ?: '-' }}</td>
                    <td>{{ $row['customer_name'] }}</td>
                    <td>{{ $row['package_name'] }}</td>
                    <td>Rp {{ number_format((float) $row['amount'], 0, ',', '.') }}</td>
                    <td>{{ $row['virtual_account_number'] ?: '-' }}</td>
                    <td>{{ $row['referral_source'] }}</td>
                    <td>{{ $row['agent_name'] ?: '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
