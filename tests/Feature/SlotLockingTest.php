<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PackageCode;
use App\Models\TableSlot;
use App\Services\SlotAllocator;
use Database\Seeders\TableSlotSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SlotLockingTest extends TestCase
{
    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = config('database.default');

        config()->set('database.default', 'pgsql');
        config()->set('database.connections.pgsql_lock_test', config('database.connections.pgsql'));

        Artisan::call('migrate', ['--force' => true]);

        TableSlot::query()->delete();
        $this->seed(TableSlotSeeder::class);
    }

    protected function tearDown(): void
    {
        TableSlot::query()->delete();
        DB::purge('pgsql');
        DB::purge('pgsql_lock_test');
        config()->set('database.default', $this->originalConnection);

        parent::tearDown();
    }

    public function test_locked_first_slot_is_skipped(): void
    {
        $lockConnection = DB::connection('pgsql_lock_test');
        $lockConnection->beginTransaction();
        $lockConnection->selectOne(
            "select id from table_slots where status = 'AVAILABLE' order by allocation_order limit 1 for update"
        );

        $result = app(SlotAllocator::class)->reserveForPackage(PackageCode::Prayer, 401);

        $lockConnection->rollBack();
        DB::purge('pgsql_lock_test');

        $this->assertSame('F18', $result['table_code']);
    }
}
