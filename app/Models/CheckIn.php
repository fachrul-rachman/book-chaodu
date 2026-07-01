<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $booking_id
 * @property int $checked_in_by
 * @property Carbon $checked_in_at
 */
#[Fillable([
    'booking_id',
    'checked_in_by',
    'checked_in_at',
])]
class CheckIn extends Model
{
    protected function casts(): array
    {
        return [
            'booking_id' => 'integer',
            'checked_in_by' => 'integer',
            'checked_in_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }
}
