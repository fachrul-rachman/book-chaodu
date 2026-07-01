<?php

namespace App\Models;

use App\Enums\ApprovalIntegrationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $booking_id
 * @property ApprovalIntegrationStatus $qr_status
 * @property string|null $qr_token_hash
 * @property string|null $qr_token_encrypted
 * @property string|null $qr_image_path
 * @property string|null $qr_error
 * @property ApprovalIntegrationStatus $drive_status
 * @property string|null $drive_external_id
 * @property string|null $drive_url
 * @property string|null $drive_error
 * @property ApprovalIntegrationStatus $notion_status
 * @property string|null $notion_external_id
 * @property string|null $notion_url
 * @property string|null $notion_error
 * @property ApprovalIntegrationStatus $approval_email_status
 * @property Carbon|null $approval_email_sent_at
 * @property string|null $approval_email_error
 * @property string|null $last_error
 */
#[Fillable([
    'booking_id',
    'qr_status',
    'qr_token_hash',
    'qr_token_encrypted',
    'qr_image_path',
    'qr_error',
    'drive_status',
    'drive_external_id',
    'drive_url',
    'drive_error',
    'notion_status',
    'notion_external_id',
    'notion_url',
    'notion_error',
    'approval_email_status',
    'approval_email_sent_at',
    'approval_email_error',
    'last_error',
])]
class ApprovalIntegration extends Model
{
    protected function casts(): array
    {
        return [
            'booking_id' => 'integer',
            'qr_status' => ApprovalIntegrationStatus::class,
            'drive_status' => ApprovalIntegrationStatus::class,
            'notion_status' => ApprovalIntegrationStatus::class,
            'approval_email_status' => ApprovalIntegrationStatus::class,
            'approval_email_sent_at' => 'datetime',
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
