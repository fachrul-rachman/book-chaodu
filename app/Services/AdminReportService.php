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
            ->map(fn (Booking $booking): array => $this->financeRow($bookin))
            ->values();

        $internalRows = collect
    }