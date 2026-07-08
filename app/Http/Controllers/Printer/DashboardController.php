<?php

namespace App\Http\Controllers\Printer;

use App\Enums\BookingStatus;
use App\Enums\PrayerPaperType;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\PrayerPaper;
use App\Services\InternalCompanySlotService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly InternalCompanySlotService $internalCompanySlotService,
    ) {}

    public function __invoke(Request $request): Response
    {
        $selectedFilter = $this->selectedFilter($request->query('filter'));
        $query = Booking::query()
            ->where('status', BookingStatus::Approved)
            ->with(['prayerPapers' => fn ($builder) => $builder->orderBy('type')->orderBy('sequence')])
            ->latest('approved_at')
            ->latest('id');

        match ($selectedFilter) {
            'PRINTED' => $query->where('is_printed', true),
            'UNPRINTED' => $query->where('is_printed', false),
            default => null,
        };

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
                    'package_name' => $booking->package_name_snapshot,
                    'source_label' => $this->sourceLabel($booking),
                    'approved_at' => optional($booking->approved_at)->format('d M Y H:i'),
                    'is_printed' => (bool) $booking->is_printed,
                    'prayer_papers' => $booking->prayerPapers
                        ->map(fn (PrayerPaper $paper): array => [
                            'id' => $paper->id,
                            'label' => $paper->type === PrayerPaperType::A
                                ? 'Kertas Doa '.max(1, (int) $paper->sequence)
                                : 'Kertas Hio',
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

    private function selectedFilter(mixed $value): string
    {
        if (! is_string($value)) {
            return 'ALL';
        }

        return in_array($value, ['ALL', 'UNPRINTED', 'PRINTED'], true)
            ? $value
            : 'ALL';
    }

    private function sourceLabel(Booking $booking): string
    {
        return match ($booking->referral_source) {
            'TEMAN' => 'Teman',
            'KELUARGA' => 'Keluarga',
            'MEDIA_SOSIAL' => 'Media sosial',
            'WEBSITE' => 'Website',
            'AGENT' => 'Agent',
            $this->internalCompanySlotService->sourceValue() => $this->internalCompanySlotService->sourceLabel(),
            default => '-',
        };
    }
}
