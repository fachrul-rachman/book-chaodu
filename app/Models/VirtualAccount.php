<?php

namespace App\Models;

use App\Enums\PackageCode;
use App\Enums\VirtualAccountStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'package_code',
    'account_number',
    'status',
    'hold_reference',
    'hold_expires_at',
    'booking_id',
])]
class VirtualAccount extends Model
{
    protected function casts(): array
    {
        return [
            'package_code' => PackageCode::class,
            'status' => VirtualAccountStatus::class,
            'hold_expires_at' => 'datetime',
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
