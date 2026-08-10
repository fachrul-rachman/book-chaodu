<?php

namespace App\Models;

use App\Enums\GalleryArchiveStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $booking_id
 * @property string $fingerprint
 * @property GalleryArchiveStatus $status
 * @property string $storage_disk
 * @property string|null $file_path
 * @property int|null $size_bytes
 * @property string|null $error_message
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $expires_at
 */
#[Fillable([
    'booking_id',
    'fingerprint',
    'status',
    'storage_disk',
    'file_path',
    'size_bytes',
    'error_message',
    'started_at',
    'completed_at',
    'expires_at',
])]
class GalleryArchive extends Model
{
    protected function casts(): array
    {
        return [
            'booking_id' => 'integer',
            'status' => GalleryArchiveStatus::class,
            'size_bytes' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
