<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'booking_id',
    'vegetarian_quantity',
    'non_vegetarian_quantity',
])]
class BookingMeal extends Model
{
    protected function casts(): array
    {
        return [
            'booking_id' => 'integer',
            'vegetarian_quantity' => 'integer',
            'non_vegetarian_quantity' => 'integer',
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
