<?php

namespace App\Http\Controllers\Printer;

use App\Enums\BookingNameCategory;
use App\Enums\BookingStatus;
use App\Enums\PrayerPaperType;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingName;
use App\Models\PrayerPaper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $selectedFilter = $this->selectedFilter($request->query('filter'));
        $query = $this->bookingQuery($selectedFilter);

        return Inertia::render('printer/dashboard', [
            'selected_filter' => $selectedFilter,
            'filter_options' => [
                ['value' => 'ALL', 'label' => 'Semua'],
                ['value' => 'UNPRINTED', 'label' => 'Belum di-print'],
                ['value' => 'PRINTED', 'label' => 'Sudah di-print'],
            ],
            'filter_counts' => [
                'ALL' => Booking::query()->where('status', BookingStatus::Approved)->count(),
                'UNPRINTED' => Booking::query()->where('status', BookingStatus::Approved)->where('is_printed', false)->count(),
                'PRINTED' => Booking::query()->where('status', BookingStatus::Approved)->where('is_printed', true)->count(),
            ],
            'bookings' => $query
                ->get()
                ->map(fn (Booking $booking): array => [
                    'id' => $booking->id,
                    'booking_number' => $booking->booking_number,
                    'customer_name' => $booking->customer_name,
                    'package_name' => $booking->package_name_snapshot,
                    'table_numbers' => $booking->tableSlots
                        ->sortBy('allocation_order')
                        ->pluck('code')
                        ->filter()
                        ->implode(', '),
                    'incense_numbers' => $booking->incenseSlots
                        ->sortBy('allocation_order')
                        ->pluck('number')
                        ->filter()
                        ->implode(', '),
                    'is_printed' => (bool) $booking->is_printed,
                    'prayer_papers' => $booking->prayerPapers
                        ->map(fn (PrayerPaper $paper): array => [
                            'id' => $paper->id,
                            'label' => $this->paperType($paper) === PrayerPaperType::A
                                ? 'Kertas Doa '.max(1, (int) $paper->sequence)
                                : 'Kertas Hio',
                            'name' => $this->paperName($booking, $paper),
                            'download_url' => $paper->file_path
                                ? route('printer.prayer-papers.show', $paper)
                                : null,
                        ])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $selectedFilter = $this->selectedFilter($request->query('filter'));
        $spreadsheet = $this->exportSpreadsheet($this->bookingQuery($selectedFilter)->get());
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(
            static fn () => $writer->save('php://output'),
            'data-printer-'.strtolower($selectedFilter).'-'.now()->format('Ymd-His').'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    /**
     * @return Builder<Booking>
     */
    private function bookingQuery(string $selectedFilter): Builder
    {
        $query = Booking::query()
            ->where('status', BookingStatus::Approved)
            ->with([
                'names',
                'tableSlots',
                'incenseSlots',
                'prayerPapers' => fn ($builder) => $builder->orderBy('type')->orderBy('sequence'),
            ])
            ->latest('approved_at')
            ->latest('id');

        match ($selectedFilter) {
            'PRINTED' => $query->where('is_printed', true),
            'UNPRINTED' => $query->where('is_printed', false),
            default => null,
        };

        return $query;
    }

    /**
     * @param  Collection<int, Booking>  $bookings
     */
    private function exportSpreadsheet(Collection $bookings): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data Printer');
        $sheet->fromArray([[
            'Nomor Booking',
            'Nama Customer',
            'Nama Alm 1',
            'Nama Alm 2',
            'Nomor Meja/Hio',
            'Nomor Telepon',
        ]], null, 'A1');

        foreach ($bookings->values() as $index => $booking) {
            $row = $index + 2;
            $names = $booking->names
                ->where('category', BookingNameCategory::Deceased)
                ->sortBy('position')
                ->values();
            $tableNumbers = $booking->tableSlots
                ->sortBy('allocation_order')
                ->pluck('code')
                ->filter()
                ->implode(', ');
            $incenseNumbers = $booking->incenseSlots
                ->sortBy('allocation_order')
                ->pluck('number')
                ->filter()
                ->implode(', ');
            $slotNumbers = array_filter([
                $tableNumbers !== '' ? 'Meja: '.$tableNumbers : null,
                $incenseNumbers !== '' ? 'Hio: '.$incenseNumbers : null,
            ]);
            $nameOne = $names->get(0);
            $nameTwo = $names->get(1);

            $sheet->fromArray([[
                $booking->booking_number,
                $booking->customer_name,
                $this->displayName($nameOne instanceof BookingName ? $nameOne : null),
                $this->displayName($nameTwo instanceof BookingName ? $nameTwo : null),
                implode(' | ', $slotNumbers) ?: '-',
                $booking->customer_phone,
            ]], null, 'A'.$row);
            $sheet->setCellValueExplicit('A'.$row, $booking->booking_number, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('F'.$row, $booking->customer_phone, DataType::TYPE_STRING);
        }

        foreach (range('A', 'F') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    private function displayName(?BookingName $name): string
    {
        if (! $name) {
            return '-';
        }

        return trim((string) $name->mandarin_name)
            ?: trim((string) $name->indonesian_name)
            ?: '-';
    }

    private function selectedFilter(mixed $value): string
    {
        if (! is_string($value)) {
            return 'ALL';
        }

        return in_array($value, ['ALL', 'UNPRINTED', 'PRINTED'], true)
            ? $value
            : 'ALL';
    }

    private function paperName(Booking $booking, PrayerPaper $paper): ?string
    {
        $type = $this->paperType($paper);
        $category = $type === PrayerPaperType::A
            ? BookingNameCategory::Deceased
            : BookingNameCategory::Incense;
        $names = $booking->names
            ->where('category', $category)
            ->sortBy('position')
            ->values();
        $name = $type === PrayerPaperType::A
            ? $names->get(max(0, ((int) $paper->sequence) - 1))
            : $names->first();

        if (! $name instanceof BookingName) {
            return null;
        }

        $displayName = trim((string) $name->mandarin_name)
            ?: trim((string) $name->indonesian_name);

        return $displayName !== '' ? $displayName : null;
    }

    private function paperType(PrayerPaper $paper): PrayerPaperType
    {
        $type = $paper->getAttribute('type');

        return $type instanceof PrayerPaperType
            ? $type
            : PrayerPaperType::from((string) $type);
    }
}
