<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminReportExportService
{
    public function __construct(
        private readonly AdminReportService $reportService,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function exportXlsx(string $tab, array $filters): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(match ($tab) {
            'finance' => 'Finance',
            'agent' => 'Agent',
            default => 'Check-in',
        });

        $row = 1;
        $sheet->setCellValue('A'.$row, $this->title($tab));
        $row++;

        foreach ($this->reportService->filterLines($filters) as $line) {
            $sheet->setCellValue('A'.$row, $line);
            $row++;
        }

        $row++;

        match ($tab) {
            'finance' => $this->writeFinanceSheet($sheet, $row, $filters),
            'agent' => $this->writeAgentSheet($sheet, $row, $filters),
            default => $this->writeCheckInSheet($sheet, $row, $filters),
        };

        foreach (range('A', $sheet->getHighestColumn()) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer): void {
            $writer->save('php://output');
        }, $this->fileName($tab, 'xlsx'), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function exportPdf(string $tab, array $filters): Response
    {
        $payload = match ($tab) {
            'finance' => $this->reportService->finance($filters),
            'agent' => $this->reportService->agent($filters),
            default => $this->reportService->checkIn($filters),
        };
        $orientation = $tab === 'checkin' ? 'landscape' : 'portrait';

        $pdf = Pdf::loadView('reports.'.$tab, [
            'title' => $this->title($tab),
            'filters' => $this->reportService->filterLines($filters),
            'generated_at' => now()->format('d-m-Y H:i'),
            'payload' => $payload,
            'app_name' => (string) config('app.name'),
        ])->setPaper('a4', $orientation);

        return $pdf->download($this->fileName($tab, 'pdf'));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function writeCheckInSheet(mixed $sheet, int $row, array $filters): void
    {
        $payload = $this->reportService->checkIn($filters);
        $headers = [
            'Nomor booking',
            'Nama customer',
            'Nomor telepon',
            'Paket',
            'Jumlah hadir',
            'Vegetarian',
            'Non vegetarian',
            'Nomor meja',
            'Nomor hio',
            'Nama agent',
            'Check-in manual',
            'Catatan',
        ];

        foreach ($headers as $index => $header) {
            $sheet->setCellValue($this->columnLetter($index + 1).$row, $header);
        }

        $row++;

        foreach ($payload['rows'] as $item) {
            $sheet->fromArray([
                $item['booking_number'],
                $item['customer_name'],
                $item['customer_phone'],
                $item['package_name'],
                $item['attendee_count'],
                $item['vegetarian_quantity'],
                $item['non_vegetarian_quantity'],
                $item['table_number'],
                $item['incense_number'],
                $item['agent_name'],
                '',
                '',
            ], null, 'A'.$row);
            $row++;
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function writeFinanceSheet(mixed $sheet, int $row, array $filters): void
    {
        $payload = $this->reportService->finance($filters);
        $sheet->setCellValue('A'.$row, 'Total booking approved');
        $sheet->setCellValue('B'.$row, $payload['summary']['total_bookings']);
        $row++;
        $sheet->setCellValue('A'.$row, 'Total uang masuk');
        $sheet->setCellValue('B'.$row, $payload['summary']['total_revenue']);
        $row += 2;

        $sheet->fromArray([
            ['Paket', 'Jumlah booking', 'Total uang masuk'],
        ], null, 'A'.$row);
        $row++;

        foreach ($payload['summary']['by_package'] as $item) {
            $sheet->fromArray([
                [
                    $item['package_name'],
                    $item['booking_count'],
                    $item['total_revenue'],
                ],
            ], null, 'A'.$row);
            $row++;
        }

        $row++;
        $sheet->fromArray([
            ['Nomor booking', 'Tanggal booking', 'Tanggal setuju', 'Nama customer', 'Paket', 'Nominal', 'Nomor VA', 'Sumber', 'Agent'],
        ], null, 'A'.$row);
        $row++;

        foreach ($payload['rows'] as $item) {
            $sheet->fromArray([
                [
                    $item['booking_number'],
                    $item['booking_date'],
                    $item['approval_date'],
                    $item['customer_name'],
                    $item['package_name'],
                    $item['amount'],
                    $item['virtual_account_number'],
                    $item['referral_source'],
                    $item['agent_name'],
                ],
            ], null, 'A'.$row);
            $row++;
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function writeAgentSheet(mixed $sheet, int $row, array $filters): void
    {
        $payload = $this->reportService->agent($filters);
        $sheet->fromArray([
            ['Nama agent', 'Jumlah booking', 'Jumlah hadir', 'Total nilai'],
        ], null, 'A'.$row);
        $row++;

        foreach ($payload['groups'] as $group) {
            $sheet->fromArray([
                [
                    $group['display_name'],
                    $group['booking_count'],
                    $group['attendee_count'],
                    $group['total_value'],
                ],
            ], null, 'A'.$row);
            $row++;
        }

        $row++;
        $sheet->fromArray([
            ['Nama agent', 'Nomor booking', 'Tanggal booking', 'Tanggal setuju', 'Nama customer', 'Paket', 'Jumlah hadir', 'Nominal'],
        ], null, 'A'.$row);
        $row++;

        foreach ($payload['groups'] as $group) {
            foreach ($group['bookings'] as $booking) {
                $sheet->fromArray([
                    [
                        $group['display_name'],
                        $booking['booking_number'],
                        $booking['booking_date'],
                        $booking['approval_date'],
                        $booking['customer_name'],
                        $booking['package_name'],
                        $booking['attendee_count'],
                        $booking['amount'],
                    ],
                ], null, 'A'.$row);
                $row++;
            }
        }
    }

    private function title(string $tab): string
    {
        return match ($tab) {
            'finance' => 'Laporan Keuangan',
            'agent' => 'Laporan Agent',
            default => 'Laporan Check-in',
        };
    }

    private function fileName(string $tab, string $extension): string
    {
        return sprintf(
            '%s-%s.%s',
            $tab,
            now()->format('Ymd-His'),
            $extension,
        );
    }

    private function columnLetter(int $index): string
    {
        $result = '';

        while ($index > 0) {
            $index--;
            $result = chr(65 + ($index % 26)).$result;
            $index = intdiv($index, 26);
        }

        return $result;
    }
}
