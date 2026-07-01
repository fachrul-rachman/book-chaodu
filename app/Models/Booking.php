<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\PrayerPaperStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $booking_number
 * @property string $idempotency_key
 * @property int $package_id
 * @property string $package_code_snapshot
 * @property string $package_name_snapshot
 * @property string $package_price_snapshot
 * @property string $customer_name
 * @property string $customer_phone
 * @property string $customer_email
 * @property int $attendee_count
 * @property string $referral_source
 * @property string|null $agent_name
 * @property BookingStatus $status
 * @property string|null $rejection_reason
 * @property int|null $approved_by
 * @property int|null $rejected_by
 * @property PrayerPaperStatus|null $prayer_paper_status
 */
#[Fillable([
    'booking_number',
    'idempotency_key',
    'package_id',
    'package_code_snapshot',
    'package_name_snapshot',
    'package_price_snapshot',
    'customer_name',
    'customer_phone',
    'customer_email',
    'attendee_count',
    'referral_source',
    'agent_name',
    'status',
    'rejection_reason',
    'approved_at',
    'approved_by',
    'rejected_at',
    'rejected_by',
    'prayer_paper_status',
    'prayer_paper_error',
    'latest_prayer_paper_generated_at',
])]
class Booking extends Model
{
    protected function casts(): array
    {
        return [
            'package_price_snapshot' => 'decimal:2',
            'attendee_count' => 'integer',
            'status' => BookingStatus::class,
            'approved_at' => 'datetime',
            'approved_by' => 'integer',
            'rejected_at' => 'datetime',
            'rejected_by' => 'integer',
            'prayer_paper_status' => PrayerPaperStatus::class,
            'latest_prayer_paper_generated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Package, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * @return HasMany<BookingName, $this>
     */
    public function names(): HasMany
    {
        return $this->hasMany(BookingName::class);
    }

    /**
     * @return HasOne<BookingMeal, $this>
     */
    public function meal(): HasOne
    {
        return $this->hasOne(BookingMeal::class);
    }

    /**
     * @return HasOne<BookingPayment, $this>
     */
    public function payment(): HasOne
    {
        return $this->hasOne(BookingPayment::class);
    }

    /**
     * @return HasOne<CheckIn, $this>
     */
    public function checkIn(): HasOne
    {
        return $this->hasOne(CheckIn::class);
    }

    /**
     * @return HasMany<PrayerPaper, $this>
     */
    public function prayerPapers(): HasMany
    {
        return $this->hasMany(PrayerPaper::class);
    }

    /**
     * @return HasOne<ApprovalIntegration, $this>
     */
    public function approvalIntegration(): HasOne
    {
        return $this->hasOne(ApprovalIntegration::class);
    }

    /**
     * @return HasOne<VirtualAccount, $this>
     */
    public function virtualAccount(): HasOne
    {
        return $this->hasOne(VirtualAccount::class);
    }

    /**
     * @return HasMany<TableSlot, $this>
     */
    public function tableSlots(): HasMany
    {
        return $this->hasMany(TableSlot::class);
    }

    /**
     * @return HasMany<IncenseSlot, $this>
     */
    public function incenseSlots(): HasMany
    {
        return $this->hasMany(IncenseSlot::class);
    }
}
