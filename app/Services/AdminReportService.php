<?php

namespace App\Services;

use App\Enums\BookingNameCategory;
use App\Enums\BookingStatus;
use App\Enums\PackageCode;
use App\Enums\PrayerPaperType;
use App\Models\Booking;
use App\Models\BookingName;
use App\Models\PrayerPaper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AdminReportService
{
    private const PER_PAGE = 25;

    public function __construct(
        private readonly InternalCompanySlotService $internalCompanySlotService,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     tab:string,
     *     date_field:string,
     *     date_from:string|null,
     *     date_to:string|null,
     *     package_code:string|null,
     *     sort:string,
     *     agent_search:string|null,
     *     page:int
     * }
     */
    public function filters(array $input): array
    {
        $tab = in_array($input['tab'] ?? null, ['checkin', 'finance', 'agent', 'customer'], true)
            ? (string) $input['tab']
            : 'checkin';

        $dateField = in_array($input['date_field'] ?? null, ['booking', 'approval'], true)
            ? (string) $input['date_field']
            : 'booking';

        $packageCode = in_array($input['package_code'] ?? null, array_map(
            fn (PackageCode $code): string => $code->value,
            PackageCode::cases(),
        ), true)
            ? (string) $input['package_code']
            : null;

        $sort = in_array($input['sort'] ?? null, [
            'table_number',
            'incense_number',
            'customer_name',
            'booking_number',
        ], true)
            ? (string) $input['sort']
            : 'table_number';

        $dateFrom = $this->nullableString($input['date_from'] ?? null);
        $dateTo = $this->nullableString($input['date_to'] ?? null);
        $agentSearch = $this->nullableString($input['agent_search'] ?? null);

        return [
            'tab' => $tab,
            'date_field' => $dateField,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'package_code' => $packageCode,
            'sort' => $sort,
            'agent_search' => $agentSearch,
            'page' => max(1, (int) ($input['page'] ?? 1)),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     rows:array<int, array<string, mixed>>,
     *     filter_lines:array<int, string>,
     *     package_options:array<int, array{value:string,label:string}>
     * }
     */
    public function checkIn(array $filters, bool $paginate = false): array
    {
        /** @var Collection<int, Booking> $bookings */
        $bookings = $this->baseQuery($filters)->get();
        $rows = $bookings
            ->map(fn (Booking $booking): array => $this->checkInRow($booking))
            ->all();

        $rows = [
            ...$rows,
            ...$this->internalCheckInRows($filters),
        ];

        $this->sortCheckInRows($rows, (string) ($filters['sort'] ?? 'table_number'));
        $paginated = $this->paginate($rows, $filters, $paginate);

        return [
            'rows' => $paginated['items'],
            'pagination' => $paginated['pagination'],
            'filter_lines' => $this->filterLines($filters),
            'package_options' => $this->packageOptions(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     summary:array{
     *         total_bookings:int,
     *         total_revenue:float,
     *         by_package:array<int, array{
     *             package_code:string,
     *             package_name:string,
     *             booking_count:int,
     *             total_revenue:float
     *         }>
     *     },
     *     rows:array<int, array<string, mixed>>,
     *     filter_lines:array<int, string>
     * }
     */
    public function finance(array $filters, bool $paginate = false): array
    {
        /** @var Collection<int, Booking> $bookings */
        $bookings = $this->baseQuery($filters)->get();
        $rows = $bookings
            ->map(fn (Booking $booking): array => $this->financeRow($booking))
            ->values();

        $internalRows = collect($this->internalFinanceRows($filters));
        $rows = $rows->concat($internalRows)->values();

        $byPackage = $rows
            ->groupBy('package_code')
            ->map(function (Collection $group): array {
                $first = $group->first();

                return [
                    'package_code' => (string) ($first['package_code'] ?? ''),
                    'package_name' => (string) ($first['package_name'] ?? ''),
                    'booking_count' => $group->count(),
                    'total_revenue' => (float) $group->sum('amount'),
                ];
            })
            ->sortBy('package_name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
        $paginated = $this->paginate($rows->all(), $filters, $paginate);

        return [
            'summary' => [
                'total_bookings' => $rows->count(),
                'total_revenue' => (float) $rows->sum('amount'),
                'by_package' => $byPackage,
            ],
            'rows' => $paginated['items'],
            'pagination' => $paginated['pagination'],
            'filter_lines' => $this->filterLines($filters),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     groups:array<int, array{
     *         key:string,
     *         display_name:string,
     *         booking_count:int,
     *         attendee_count:int,
     *         total_value:float,
     *         bookings:array<int, array<string, mixed>>
     *     }>,
     *     filter_lines:array<int, string>
     * }
     */
    public function agent(array $filters, bool $paginate = false): array
    {
        $search = $this->normalizeAgentName((string) ($filters['agent_search'] ?? ''));

        /** @var Collection<int, Booking> $bookings */
        $bookings = $this->baseQuery($filters)
            ->where('referral_source', 'AGENT')
            ->get();

        $groups = $bookings
            ->map(fn (Booking $booking): array => $this->agentBookingRow($booking))
            ->groupBy('agent_key')
            ->map(function (Collection $group): array {
                $first = $group->first();
                $bookings = $group
                    ->map(function (array $row): array {
                        unset($row['agent_key']);

                        return $row;
                    })
                    ->sortBy('booking_number', SORT_NATURAL | SORT_FLAG_CASE)
                    ->values()
                    ->all();

                return [
                    'key' => (string) ($first['agent_key'] ?? ''),
                    'display_name' => (string) ($first['agent_name'] ?? '-'),
                    'booking_count' => $group->count(),
                    'attendee_count' => (int) $group->sum('attendee_count'),
                    'total_value' => (float) $group->sum('amount'),
                    'bookings' => $bookings,
                ];
            })
            ->filter(function (array $group) use ($search): bool {
                if ($search === '') {
                    return true;
                }

                return str_contains(
                    $this->normalizeAgentName($group['display_name']),
                    $search,
                );
            })
            ->sort(function (array $left, array $right): int {
                if ($left['booking_count'] !== $right['booking_count']) {
                    return $right['booking_count'] <=> $left['booking_count'];
                }

                return strnatcasecmp($left['display_name'], $right['display_name']);
            })
            ->values()
            ->all();
        $paginated = $this->paginate($groups, $filters, $paginate);

        return [
            'groups' => $paginated['items'],
            'pagination' => $paginated['pagination'],
            'filter_lines' => $this->filterLines($filters),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     rows:array<int, array<string, mixed>>,
     *     filter_lines:array<int, string>
     * }
     */
    public function customer(array $filters, bool $paginate = false): array
    {
        /** @var Collection<int, Booking> $bookings */
        $bookings = $this->baseQuery($filters)
            ->with(['names', 'prayerPapers'])
            ->get();

        $rows = $bookings
            ->map(fn (Booking $booking): array => $this->customerRow($booking))
            ->values()
            ->all();
        $paginated = $this->paginate($rows, $filters, $paginate);

        return [
            'rows' => $paginated['items'],
            'pagination' => $paginated['pagination'],
            'filter_lines' => $this->filterLines($filters),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Booking>
     */
    public function baseQuery(array $filters): Builder
    {
        $query = Booking::query()
            ->where('status', BookingStatus::Approved)
            ->with(['meal', 'payment', 'tableSlots', 'incenseSlots', 'checkIn']);

        if (! blank($filters['package_code'] ?? null)) {
            $query->where('package_code_snapshot', (string) $filters['package_code']);
        }

        $dateColumn = ($filters['date_field'] ?? 'booking') === 'approval'
            ? 'approved_at'
            : 'created_at';

        if (! blank($filters['date_from'] ?? null)) {
            $query->whereDate($dateColumn, '>=', (string) $filters['date_from']);
        }

        if (! blank($filters['date_to'] ?? null)) {
            $query->whereDate($dateColumn, '<=', (string) $filters['date_to']);
        }

        return $query->orderBy('id');
    }

    /**
     * @return array<int, array{value:string,label:string}>
     */
    public function packageOptions(): array
    {
        return array_map(
            fn (PackageCode $code): array => [
                'value' => $code->value,
                'label' => $code->label(),
            ],
            PackageCode::cases(),
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, string>
     */
    public function filterLines(array $filters): array
    {
        $lines = [];
        $dateLabel = ($filters['date_field'] ?? 'booking') === 'approval'
            ? 'Tanggal setuju'
            : 'Tanggal booking';

        $lines[] = 'Pakai tanggal: '.$dateLabel;
        $lines[] = 'Dari: '.($filters['date_from'] ?: '-');
        $lines[] = 'Sampai: '.($filters['date_to'] ?: '-');
        $lines[] = 'Paket: '.$this->packageLabel($filters['package_code'] ?? null);

        if (! blank($filters['agent_search'] ?? null)) {
            $lines[] = 'Cari agent: '.(string) $filters['agent_search'];
        }

        return $lines;
    }

    public function packageLabel(mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            return 'Semua';
        }

        return PackageCode::tryFrom($value)?->label() ?? 'Semua';
    }

    public function normalizeAgentName(?string $value): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim((string) $value));

        return $normalized ? mb_strtolower($normalized) : '';
    }

    /**
     * @return array<string, mixed>
     */
    private function checkInRow(Booking $booking): array
    {
        $meal = $booking->meal;
        $tableCodes = $booking->tableSlots
            ->sortBy('allocation_order')
            ->pluck('code')
            ->filter()
            ->values()
            ->all();

        $incenseNumbers = $booking->incenseSlots
            ->sortBy('allocation_order')
            ->pluck('number')
            ->filter()
            ->values()
            ->all();

        return [
            'booking_number' => $booking->booking_number,
            'customer_name' => $booking->customer_name,
            'customer_phone' => $booking->customer_phone,
            'package_name' => $booking->package_name_snapshot,
            'attendee_count' => $booking->attendee_count,
            'vegetarian_quantity' => $meal ? $meal->vegetarian_quantity : 0,
            'non_vegetarian_quantity' => $meal ? $meal->non_vegetarian_quantity : 0,
            'table_number' => implode(', ', $tableCodes),
            'incense_number' => implode(', ', array_map(
                fn (mixed $number): string => (string) $number,
                $incenseNumbers,
            )),
            'agent_name' => $booking->agent_name,
            'manual_check_in' => '',
            'notes' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function financeRow(Booking $booking): array
    {
        $payment = $booking->payment;

        return [
            'booking_number' => $booking->booking_number,
            'booking_date' => optional($booking->created_at)->toDateString(),
            'approval_date' => optional($booking->approved_at)->toDateString(),
            'customer_name' => $booking->customer_name,
            'package_code' => $booking->package_code_snapshot,
            'package_name' => $booking->package_name_snapshot,
            'amount' => (float) ($payment ? $payment->transferred_amount : 0),
            'virtual_account_number' => $payment?->virtual_account_number,
            'referral_source' => $this->referralSourceLabel($booking->referral_source),
            'agent_name' => $booking->agent_name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function agentBookingRow(Booking $booking): array
    {
        $payment = $booking->payment;
        $normalized = $this->normalizedDisplayName($booking->agent_name);

        return [
            'agent_key' => $this->normalizeAgentName($booking->agent_name),
            'agent_name' => $normalized,
            'booking_number' => $booking->booking_number,
            'booking_date' => optional($booking->created_at)->toDateString(),
            'approval_date' => optional($booking->approved_at)->toDateString(),
            'customer_name' => $booking->customer_name,
            'package_name' => $booking->package_name_snapshot,
            'attendee_count' => $booking->attendee_count,
            'amount' => (float) ($payment ? $payment->transferred_amount : 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function customerRow(Booking $booking): array
    {
        $deceasedNames = $booking->names
            ->where('category', BookingNameCategory::Deceased)
            ->keyBy('position');
        $incenseName = $booking->names
            ->first(fn (BookingName $name): bool => $name->category === BookingNameCategory::Incense);
        $papers = $booking->prayerPapers->keyBy(
            fn (PrayerPaper $paper): string => $paper->getRawOriginal('type').':'.$paper->sequence,
        );

        return [
            'booking_number' => $booking->booking_number,
            'customer_name' => $booking->customer_name,
            'customer_phone' => $booking->customer_phone,
            'customer_email' => $booking->customer_email,
            'package_name' => $booking->package_name_snapshot,
            'prayer_paper_1' => $this->customerPaper(
                $deceasedNames->get(1),
                $papers->get(PrayerPaperType::A->value.':1'),
            ),
            'prayer_paper_2' => $this->customerPaper(
                $deceasedNames->get(2),
                $papers->get(PrayerPaperType::A->value.':2'),
            ),
            'incense_paper' => $this->customerPaper(
                $incenseName,
                $papers->get(PrayerPaperType::B->value.':1'),
            ),
        ];
    }

    /**
     * @return array{name:string|null,image_url:string|null}
     */
    private function customerPaper(?BookingName $name, ?PrayerPaper $paper): array
    {
        $mandarinName = trim((string) ($name?->mandarin_name ?? ''));
        $indonesianName = trim((string) ($name?->indonesian_name ?? ''));
        $displayName = $mandarinName !== '' ? $mandarinName : $indonesianName;

        return [
            'name' => $displayName !== '' ? $displayName : null,
            'image_url' => filled($paper?->file_path)
                ? route('admin.prayer-papers.show', $paper)
                : null,
        ];
    }

    /**
     * @param  array<int, mixed>  $items
     * @param  array<string, mixed>  $filters
     * @return array{
     *     items:array<int, mixed>,
     *     pagination:array{
     *         current_page:int,
     *         last_page:int,
     *         per_page:int,
     *         total:int,
     *         from:int|null,
     *         to:int|null
     *     }
     * }
     */
    private function paginate(array $items, array $filters, bool $enabled): array
    {
        $total = count($items);

        if (! $enabled) {
            return [
                'items' => $items,
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => self::PER_PAGE,
                    'total' => $total,
                    'from' => $total > 0 ? 1 : null,
                    'to' => $total > 0 ? $total : null,
                ],
            ];
        }

        $lastPage = max(1, (int) ceil($total / self::PER_PAGE));
        $currentPage = min(max(1, (int) ($filters['page'] ?? 1)), $lastPage);
        $offset = ($currentPage - 1) * self::PER_PAGE;
        $pageItems = array_slice($items, $offset, self::PER_PAGE);

        return [
            'items' => $pageItems,
            'pagination' => [
                'current_page' => $currentPage,
                'last_page' => $lastPage,
                'per_page' => self::PER_PAGE,
                'total' => $total,
                'from' => $total > 0 ? $offset + 1 : null,
                'to' => $total > 0 ? min($offset + count($pageItems), $total) : null,
            ],
        ];
    }

    private function normalizedDisplayName(?string $value): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim((string) $value));

        return $normalized ?: '-';
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function sortCheckInRows(array &$rows, string $sort): void
    {
        usort($rows, function (array $left, array $right) use ($sort): int {
            return match ($sort) {
                'incense_number' => $this->compareNullable(
                    $left['incense_number'] ?? null,
                    $right['incense_number'] ?? null,
                ),
                'customer_name' => strnatcasecmp(
                    (string) ($left['customer_name'] ?? ''),
                    (string) ($right['customer_name'] ?? ''),
                ),
                'booking_number' => strnatcasecmp(
                    (string) ($left['booking_number'] ?? ''),
                    (string) ($right['booking_number'] ?? ''),
                ),
                default => $this->compareNullable(
                    $left['table_number'] ?? null,
                    $right['table_number'] ?? null,
                ),
            };
        });
    }

    private function compareNullable(mixed $left, mixed $right): int
    {
        $leftValue = trim((string) $left);
        $rightValue = trim((string) $right);

        if ($leftValue === '' && $rightValue === '') {
            return 0;
        }

        if ($leftValue === '') {
            return 1;
        }

        if ($rightValue === '') {
            return -1;
        }

        return strnatcasecmp($leftValue, $rightValue);
    }

    private function referralSourceLabel(?string $value): string
    {
        return match ($value) {
            'INTERNAL_PERUSAHAAN' => $this->internalCompanySlotService->sourceLabel(),
            'TEMAN' => 'Teman',
            'KELUARGA' => 'Keluarga',
            'MEDIA_SOSIAL' => 'Media sosial',
            'WEBSITE' => 'Website',
            'AGENT' => 'Agent',
            default => '-',
        };
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    private function internalCheckInRows(array $filters): array
    {
        return array_map(
            fn (array $row): array => [
                'booking_number' => $row['booking_number'],
                'customer_name' => $row['customer_name'],
                'customer_phone' => $row['customer_phone'],
                'package_name' => $row['package_name'],
                'attendee_count' => $row['attendee_count'],
                'vegetarian_quantity' => $row['vegetarian_quantity'],
                'non_vegetarian_quantity' => $row['non_vegetarian_quantity'],
                'table_number' => $row['table_number'],
                'incense_number' => $row['incense_number'],
                'agent_name' => $row['agent_name'],
                'manual_check_in' => '',
                'notes' => '',
            ],
            $this->filteredInternalRows($filters),
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    private function internalFinanceRows(array $filters): array
    {
        return array_map(
            fn (array $row): array => [
                'booking_number' => $row['booking_number'],
                'booking_date' => $row['booking_date'],
                'approval_date' => $row['approval_date'],
                'customer_name' => $row['customer_name'],
                'package_code' => $row['package_code'],
                'package_name' => $row['package_name'],
                'amount' => $row['amount'],
                'virtual_account_number' => $row['virtual_account_number'],
                'referral_source' => $row['referral_source'],
                'agent_name' => $row['agent_name'],
            ],
            $this->filteredInternalRows($filters),
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    private function filteredInternalRows(array $filters): array
    {
        $packageCode = $filters['package_code'] ?? null;

        return array_values(array_filter(
            $this->internalCompanySlotService->reportRows(),
            static fn (array $row): bool => blank($packageCode) || $row['package_code'] === $packageCode,
        ));
    }
}
