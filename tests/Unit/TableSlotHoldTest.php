<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\TableSlot;
use Tests\TestCase;

class TableSlotHoldTest extends TestCase
{
    public function test_e_and_j_tables_from_88_can_be_temporarily_held_with_configuration(): void
    {
        config()->set('table_slots.hold_ej_from_88', true);

        $this->assertFalse($this->slot('E', 78)->isTemporarilyClosed());
        $this->assertTrue($this->slot('E', 88)->isTemporarilyClosed());
        $this->assertTrue($this->slot('J', 258)->isTemporarilyClosed());
        $this->assertFalse($this->slot('H', 88)->isTemporarilyClosed());

        config()->set('table_slots.hold_ej_from_88', false);

        $this->assertFalse($this->slot('E', 88)->isTemporarilyClosed());
    }

    public function test_disabled_hold_scope_remains_valid_inside_an_or_condition(): void
    {
        config()->set('table_slots.hold_ej_from_88', false);

        $sql = TableSlot::query()
            ->where(function ($query): void {
                $query
                    ->where('booking_id', 72)
                    ->orWhere(fn ($query) => $query->notTemporarilyClosed());
            })
            ->toSql();

        $this->assertStringContainsString('TRUE', $sql);
    }

    private function slot(string $rowCode, int $number): TableSlot
    {
        return (new TableSlot)->forceFill([
            'code' => $rowCode.$number,
            'row_code' => $rowCode,
            'number' => $number,
        ]);
    }
}
