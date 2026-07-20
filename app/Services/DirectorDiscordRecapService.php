<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\PackageCode;
use App\Enums\SlotStatus;
use App\Models\Booking;
use App\Models\IncenseSlot;
use App\Models\TableSlot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DirectorDiscordRecapService
{
    public const STATUS_SENT = 'sent';

    public const STATUS_NO_NEW_BOOKING = 'no_new_booking';

    public const STATUS_ALREADY_SENT = 'already_sent';

    public const STATUS_NOT_CONFIGURED = 'not_configured';

    public const STATUS_FAILED = 'failed';

    public function __construct(
        private readonly InternalCompanySlotService $internalCompanySlotService,
    ) {}

    public function sendLatest(): string
    {
        $now = CarbonImmutable::now((string) config('app.timezone'));
        $todayCutoff = $now->setTime(18, 0);
        $periodEnd = $now->lessThan($todayCutoff)
            ? $todayCutoff->subDay()
            : $todayCutoff;

        return $this->sendForPeriod($periodEnd);
    }

    public function sendForPeriod(CarbonImmutable $periodEnd): string
    {
        $url = trim((string) config('services.discord.director_recap_webhook_url'));

        if ($url === '') {
            return self::STATUS_NOT_CONFIGURED;
        }

        $periodEnd = $periodEnd->timezone((string) config('app.timezone'));
        $periodStart = $periodEnd->subDay();
        $sentKey = 'discord:director-recap:'.$periodEnd->format('Y-m-d-H-i');

        if (Cache::has($sentKey)) {
            return self::STATUS_ALREADY_SENT;
        }

        return Cache::lock($sentKey.':lock', 30)->block(5, function () use (
            $periodStart,
            $periodEnd,
            $sentKey,
            $url,
        ): string {
            if (Cache::has($sentKey)) {
                return self::STATUS_ALREADY_SENT;
            }

            $newApprovedCount = Booking::query()
                ->where('status', BookingStatus::Approved)
                ->where('approved_at', '>=', $periodStart)
                ->where('approved_at', '<', $periodEnd)
                ->count();

            if ($newApprovedCount === 0) {
                return self::STATUS_NO_NEW_BOOKING;
            }

            /** @var Collection<int, Booking> $approvedBookings */
            $approvedBookings = Booking::query()
                ->where('status', BookingStatus::Approved)
                ->where('approved_at', '<', $periodEnd)
                ->with(['payment', 'meal'])
                ->get();

            try {
                Http::asJson()
                    ->timeout(max(1, (int) config('services.discord.timeout_seconds', 5)))
                    ->retry(max(0, (int) config('services.discord.retry_times', 1)), 200)
                    ->post($url, $this->payload(
                        $periodStart,
                        $periodEnd,
                        $newApprovedCount,
                        $approvedBookings,
                    ))
                    ->throw();
            } catch (\Throwable $throwable) {
                Log::warning('Gagal mengirim rekapan Discord direksi.', [
                    'period_end' => $periodEnd->toIso8601String(),
                    'message' => $throwable->getMessage(),
                ]);

                return self::STATUS_FAILED;
            }

            Cache::put($sentKey, true, now()->addYear());

            return self::STATUS_SENT;
        });
    }

    public function sendCurrentSnapshot(): string
    {
        $url = trim((string) config('services.discord.director_recap_webhook_url'));

        if ($url === '') {
            return self::STATUS_NOT_CONFIGURED;
        }

        /** @var Collection<int, Booking> $approvedBookings */
        $approvedBookings = Booking::query()
            ->where('status', BookingStatus::Approved)
            ->with(['payment', 'meal'])
            ->get();
        $generatedAt = CarbonImmutable::now((string) config('app.timezone'));

        try {
            Http::asJson()
                ->timeout(max(1, (int) config('services.discord.timeout_seconds', 5)))
                ->retry(max(0, (int) config('services.discord.retry_times', 1)), 200)
                ->post($url, $this->currentSnapshotPayload($generatedAt, $approvedBookings))
                ->throw();
        } catch (\Throwable $throwable) {
            Log::warning('Gagal mengirim rekapan manual Discord direksi.', [
                'generated_at' => $generatedAt->toIso8601String(),
                'message' => $throwable->getMessage(),
            ]);

            return self::STATUS_FAILED;
        }

        return self::STATUS_SENT;
    }

    /**
     * @param  Collection<int, Booking>  $approvedBookings
     * @return array<string, mixed>
     */
    private function payload(
        CarbonImmutable $periodStart,
        CarbonImmutable $periodEnd,
        int $newApprovedCount,
        Collection $approvedBookings,
    ): array {
        $totalRevenue = $approvedBookings->sum(
            fn (Booking $booking): int => (int) ($booking->payment?->transferred_amount ?? 0),
        );
        $vegetarianTotal = $approvedBookings->sum(
            fn (Booking $booking): int => (int) ($booking->meal?->vegetarian_quantity ?? 0),
        );
        $nonVegetarianTotal = $approvedBookings->sum(
            fn (Booking $booking): int => (int) ($booking->meal?->non_vegetarian_quantity ?? 0),
        );
        $packageLines = collect(PackageCode::cases())
            ->map(function (PackageCode $packageCode) use ($approvedBookings): ?string {
                $bookings = $approvedBookings->where('package_code_snapshot', $packageCode->value);

                if ($bookings->isEmpty()) {
                    return null;
                }

                $amount = $bookings->sum(
                    fn (Booking $booking): int => (int) ($booking->payment?->transferred_amount ?? 0),
                );

                return sprintf(
                    '%s: %d | %s',
                    $packageCode->label(),
                    $bookings->count(),
                    $this->formatCurrency($amount),
                );
            })
            ->filter()
            ->implode("\n");
        $assignedTables = TableSlot::query()->where('status', SlotStatus::Assigned)->count();
        $availableTables = TableSlot::query()
            ->where('status', SlotStatus::Available)
            ->whereNotIn('code', $this->internalCompanySlotService->tableCodes())
            ->count();
        $assignedIncense = IncenseSlot::query()->where('status', SlotStatus::Assigned)->count();
        $availableIncense = IncenseSlot::query()
            ->where('status', SlotStatus::Available)
            ->whereNotIn('number', $this->internalCompanySlotService->incenseNumbers())
            ->count();
        $pendingCount = Booking::query()
            ->where('status', BookingStatus::Pending)
            ->whereHas('payment')
            ->count();

        return [
            'username' => (string) config('services.discord.username', config('app.name')),
            'embeds' => [[
                'title' => '📊 Rekapan Booking Chao Du',
                'color' => 3447003,
                'author' => [
                    'name' => (string) config('app.name'),
                ],
                'fields' => [
                    $this->field(
                        '📅 Periode',
                        $periodStart->format('d-m-Y H:i').' sampai '.$periodEnd->format('d-m-Y H:i'),
                    ),
                    $this->field('🆕 Booking baru disetujui', (string) $newApprovedCount),
                    $this->field('✅ Total booking disetujui', (string) $approvedBookings->count()),
                    $this->field('💰 Total pemasukan', $this->formatCurrency($totalRevenue)),
                    $this->field(
                        '👥 Total peserta',
                        (string) $approvedBookings->sum('attendee_count').' orang',
                    ),
                    $this->field(
                        '🍱 Makanan',
                        "Vegetarian: {$vegetarianTotal}\nNonvegetarian: {$nonVegetarianTotal}",
                    ),
                    $this->field('📦 Rincian paket', $packageLines ?: '-'),
                    $this->field(
                        '🪑 Meja',
                        "Terisi: {$assignedTables}\nTersisa: {$availableTables}",
                    ),
                    $this->field(
                        '🧧 Hio',
                        "Terisi: {$assignedIncense}\nTersisa: {$availableIncense}",
                    ),
                    $this->field(
                        '🧑‍💼 Booking dari agent',
                        (string) $approvedBookings->where('referral_source', 'AGENT')->count(),
                    ),
                    $this->field('⏳ Menunggu persetujuan', (string) $pendingCount),
                    $this->field(
                        '🏢 Internal perusahaan',
                        count($this->internalCompanySlotService->tableCodes())
                            ." meja\n"
                            .count($this->internalCompanySlotService->incenseNumbers())
                            .' hio',
                    ),
                ],
                'footer' => [
                    'text' => (string) config('app.name'),
                ],
                'timestamp' => $periodEnd->toIso8601String(),
            ]],
        ];
    }

    /**
     * @param  Collection<int, Booking>  $approvedBookings
     * @return array<string, mixed>
     */
    private function currentSnapshotPayload(
        CarbonImmutable $generatedAt,
        Collection $approvedBookings,
    ): array {
        $totalRevenue = $approvedBookings->sum(
            fn (Booking $booking): int => (int) ($booking->payment?->transferred_amount ?? 0),
        );
        $vegetarianTotal = $approvedBookings->sum(
            fn (Booking $booking): int => (int) ($booking->meal?->vegetarian_quantity ?? 0),
        );
        $nonVegetarianTotal = $approvedBookings->sum(
            fn (Booking $booking): int => (int) ($booking->meal?->non_vegetarian_quantity ?? 0),
        );
        $packageLines = collect(PackageCode::cases())
            ->map(function (PackageCode $packageCode) use ($approvedBookings): ?string {
                $bookings = $approvedBookings->where('package_code_snapshot', $packageCode->value);

                if ($bookings->isEmpty()) {
                    return null;
                }

                $amount = $bookings->sum(
                    fn (Booking $booking): int => (int) ($booking->payment?->transferred_amount ?? 0),
                );

                return sprintf(
                    '%s: %d | %s',
                    $packageCode->label(),
                    $bookings->count(),
                    $this->formatCurrency($amount),
                );
            })
            ->filter()
            ->implode("\n");

        return [
            'username' => (string) config('services.discord.username', config('app.name')),
            'embeds' => [[
                'title' => '📊 Rekapan Keseluruhan Chao Du',
                'color' => 15844367,
                'author' => [
                    'name' => (string) config('app.name'),
                ],
                'fields' => [
                    $this->field(
                        '🕒 Dibuat pada',
                        $generatedAt->format('d-m-Y H:i'),
                    ),
                    $this->field('✅ Booking disetujui', (string) $approvedBookings->count()),
                    $this->field('💰 Total pemasukan', $this->formatCurrency($totalRevenue)),
                    $this->field(
                        '👥 Total peserta',
                        (string) $approvedBookings->sum('attendee_count').' orang',
                    ),
                    $this->field(
                        '🍱 Makanan',
                        "Vegetarian: {$vegetarianTotal}\nNonvegetarian: {$nonVegetarianTotal}",
                    ),
                    $this->field('📦 Rincian paket', $packageLines ?: '-'),
                    $this->field(
                        '🧑‍💼 Booking dari agent',
                        (string) $approvedBookings->where('referral_source', 'AGENT')->count(),
                    ),
                ],
                'footer' => [
                    'text' => (string) config('app.name'),
                ],
                'timestamp' => $generatedAt->toIso8601String(),
            ]],
        ];
    }

    /**
     * @return array{name:string,value:string,inline:false}
     */
    private function field(string $name, string $value): array
    {
        return [
            'name' => $name,
            'value' => $value,
            'inline' => false,
        ];
    }

    private function formatCurrency(int $amount): string
    {
        return 'Rp'.number_format($amount, 0, ',', '.');
    }
}
