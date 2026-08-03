<?php

namespace App\Models;

use App\Enums\SlotStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $code
 * @property string $row_code
 * @property int $number
 * @property int $allocation_order
 * @property SlotStatus $status
 * @property int|null $booking_id
 */
#[Fillable([
    'code',
    'row_code',
    'number',
    'allocation_order',
    'status',
    'booking_id',
])]
class TableSlot extends Model
{
    /**
     * @param  Builder<TableSlot>  $query
     * @return Builder<TableSlot>
     */
    public function scopeNotTemporarilyClosed(Builder $query): Builder
    {
        $holdEjFrom88 = (bool) config('table_slots.hold_ej_from_88', true);
        $holdCodes = self::holdCodes();

        if (! $holdEjFrom88 && $holdCodes === []) {
            return $query->whereRaw('TRUE');
        }

        if ($holdEjFrom88) {
            $query->where(function (Builder $query): void {
                $query
                    ->whereNotIn('row_code', ['E', 'J'])
                    ->orWhere('number', '<', 88);
            });
        }

        if ($holdCodes !== []) {
            $query->whereNotIn('code', $holdCodes);
        }

        return $query;
    }

    public function isTemporarilyClosed(): bool
    {
        return (
            (bool) config('table_slots.hold_ej_from_88', true)
            && in_array($this->row_code, ['E', 'J'], true)
            && $this->number >= 88
        ) || in_array(strtoupper($this->code), self::holdCodes(), true);
    }

    /** @return array<int, string> */
    private static function holdCodes(): array
    {
        $codes = config('table_slots.hold_codes', []);

        return is_array($codes) ? array_values($codes) : [];
    }

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'allocation_order' => 'integer',
            'status' => SlotStatus::class,
            'booking_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
