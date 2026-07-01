<?php

namespace App\Models;

use App\Enums\BookingNameCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $booking_id
 * @property BookingNameCategory $category
 * @property int $position
 * @property string|null $indonesian_name
 * @property string|null $mandarin_name
 * @property string|null $mandarin_source_image_path
 * @property int|null $updated_by
 */
#[Fillable([
    'booking_id',
    'category',
    'position',
    'indonesian_name',
    'mandarin_name',
    'mandarin_source_image_path',
    'updated_by',
])]
class BookingName extends Model
{
    protected function casts(): array
    {
        return [
            'booking_id' => 'integer',
            'category' => BookingNameCategory::class,
            'position' => 'integer',
            'updated_by' => 'integer',
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
