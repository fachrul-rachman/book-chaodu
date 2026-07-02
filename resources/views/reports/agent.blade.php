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

    <h2>Ringkasan Agent</h2>
    <table>
        <thead>
            <tr>
                <th>Nama agent</th>
                <th>Jumlah booking</th>
                <th>Jumlah hadir</th>
                <th>Total nilai</th>
            </tr>
        </thead>
        <tbody>
            @foreach (($payload['groups'] ?? []) as $group)
                <tr>
                    <td>{{ $group['display_name'] }}</td>
                    <td>{{ $group['booking_count'] }}</td>
                    <td>{{ $group['attendee_count'] }}</td>
                    <td>Rp {{ number_format((float) $group['total_value'], 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Detail Booking</h2>
    <table>
        <thead>
            <tr>
                <th>Nama agent</th>
                <th>Nomor booking</th>
                <th>Tanggal booking</th>
                <th>Tanggal setuju</th>
                <th>Nama customer</th>
                <th>Paket</th>
                <th>Jumlah hadir</th>
                <th>Nominal</th>
            </tr>
        </thead>
        <tbody>
            @foreach (($payload['groups'] ?? []) as $group)
                @foreach (($group['bookings'] ?? []) as $booking)
                    <tr>
                        <td>{{ $group['display_name'] }}</td>
                        <td>{{ $booking['booking_number'] }}</td>
                        <td>{{ $booking['booking_date'] ?: '-' }}</td>
                        <td>{{ $booking['approval_date'] ?: '-' }}</td>
                        <td>{{ $booking['customer_name'] }}</td>
                        <td>{{ $booking['package_name'] }}</td>
                        <td>{{ $booking['attendee_count'] }}</td>
                        <td>Rp {{ number_format((float) $booking['amount'], 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>
</body>
</html>
