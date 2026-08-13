<?php

namespace App\Models;

use App\Enums\GalleryMediaScope;
use App\Enums\GalleryMediaStatus;
use App\Enums\GalleryMediaType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $uuid
 * @property GalleryMediaScope $scope
 * @property int|null $booking_id
 * @property int|null $source_prayer_paper_id
 * @property int|null $source_table_layout_booking_id
 * @property GalleryMediaType $media_type
 * @property GalleryMediaStatus $status
 * @property string $storage_disk
 * @property string $original_path
 * @property string|null $preview_path
 * @property string|null $thumbnail_path
 * @property string $original_filename
 * @property string $stored_filename
 * @property string $mime_type
 * @property string $extension
 * @property int $size_bytes
 * @property int|null $width
 * @property int|null $height
 * @property string|null $duration_seconds
 * @property string|null $caption
 * @property int|null $sort_order
 * @property string|null $upload_mode
 * @property string|null $upload_token
 * @property string|null $upload_id
 * @property string|null $error_message
 * @property int|null $uploaded_by
 */
#[Fillable([
    'uuid',
    'scope',
    'booking_id',
    'source_prayer_paper_id',
    'source_table_layout_booking_id',
    'media_type',
    'status',
    'storage_disk',
    'original_path',
    'preview_path',
    'thumbnail_path',
    'original_filename',
    'stored_filename',
    'mime_type',
    'extension',
    'size_bytes',
    'width',
    'height',
    'duration_seconds',
    'caption',
    'sort_order',
    'upload_token',
    'upload_mode',
    'upload_id',
    'upload_expires_at',
    'error_message',
    'uploaded_by',
    'published_at',
])]
class GalleryMedia extends Model
{
    protected $table = 'gallery_media';

    protected function casts(): array
    {
        return [
            'scope' => GalleryMediaScope::class,
            'booking_id' => 'integer',
            'source_prayer_paper_id' => 'integer',
            'source_table_layout_booking_id' => 'integer',
            'media_type' => GalleryMediaType::class,
            'status' => GalleryMediaStatus::class,
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'duration_seconds' => 'decimal:3',
            'sort_order' => 'integer',
            'upload_expires_at' => 'datetime',
            'uploaded_by' => 'integer',
            'published_at' => 'datetime',
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
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
