<?php

namespace App\Models;

use App\Enums\PrayerPaperStatus;
use App\Enums\PrayerPaperType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'booking_id',
    'type',
    'sequence',
    'file_path',
    'version',
    'status',
    'error_message',
    'generated_at',
])]
class PrayerPaper extends Model
{
    protected function casts(): array
    {
        return [
            'booking_id' => 'integer',
            'type' => PrayerPaperType::class,
            'sequence' => 'integer',
            'version' => 'integer',
            'status' => PrayerPaperStatus::class,
            'generated_at' => 'datetime',
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
