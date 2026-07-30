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
        if (! config('table_slots.hold_ej_from_88', true)) {
            return $query->whereRaw('TRUE');
        }

        return $query->where(function (Builder $query): void {
            $query
                ->whereNotIn('row_code', ['E', 'J'])
                ->orWhere('number', '<', 88);
        });
    }

    public function isTemporarilyClosed(): bool
    {
        return (bool) config('table_slots.hold_ej_from_88', true)
            && in_array($this->row_code, ['E', 'J'], true)
            && $this->number >= 88;
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
