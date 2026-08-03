<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\DirectorDiscordRecapService;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

class DirectorDiscordRecapPeriodTest extends TestCase
{
    #[DataProvider('reportingTimes')]
    public function test_it_resolves_the_latest_director_reporting_period(
        string $now,
        string $expectedStart,
        string $expectedEnd,
    ): void {
        $service = app(DirectorDiscordRecapService::class);
        $latestPeriodEnd = new ReflectionMethod($service, 'latestPeriodEnd');
        $periodStart = new ReflectionMethod($service, 'periodStart');

        $end = $latestPeriodEnd->invoke($service, CarbonImmutable::parse($now, 'Asia/Jakarta'));
        $start = $periodStart->invoke($service, $end);

        $this->assertSame($expectedStart, $start->format('Y-m-d H:i:s'));
        $this->assertSame($expectedEnd, $end->format('Y-m-d H:i:s'));
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function reportingTimes(): array
    {
        return [
            'before noon uses yesterday evening' => [
                '2026-08-03 11:59:59',
                '2026-08-02 12:00:00',
                '2026-08-02 20:00:00',
            ],
            'noon covers yesterday evening until noon' => [
                '2026-08-03 12:00:00',
                '2026-08-02 20:00:00',
                '2026-08-03 12:00:00',
            ],
            'before evening still uses noon cutoff' => [
                '2026-08-03 19:59:59',
                '2026-08-02 20:00:00',
                '2026-08-03 12:00:00',
            ],
            'evening covers noon until evening' => [
                '2026-08-03 20:00:00',
                '2026-08-03 12:00:00',
                '2026-08-03 20:00:00',
            ],
        ];
    }
}
