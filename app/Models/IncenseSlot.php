<?php

namespace App\Models;

use App\Enums\SlotStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $number
 * @property int $allocation_order
 * @property SlotStatus $status
 * @property int|null $booking_id
 */
#[Fillable([
    'number',
    'allocation_order',
    'status',
    'booking_id',
])]
class IncenseSlot extends Model
{
    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'allocation_order' => 'integer',
            'status' => SlotStatus::class,
            'booking_id' => 'integer',
        ];
    }
}
