<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\CheckerLookupService;
use PHPUnit\Framework\TestCase;

class CheckerLookupServiceTest extends TestCase
{
    public function test_it_normalizes_common_indonesian_phone_formats(): void
    {
        $service = new CheckerLookupService;

        self::assertSame('6281234567890', $service->normalizePhone('0812-3456-7890'));
        self::assertSame('6281234567890', $service->normalizePhone('812 3456 7890'));
        self::assertSame('6281234567890', $service->normalizePhone('+62 812-3456-7890'));
        self::assertNull($service->normalizePhone('LIMEI'));
    }
}
