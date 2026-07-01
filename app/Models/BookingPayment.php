<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'booking_id',
    'expected_amount',
    'sender_name',
    'transferred_amount',
    'transfer_date',
    'proof_path',
    'virtual_account_bank_name',
    'virtual_account_number',
    'virtual_account_holder',
    'admin_note',
    'updated_by',
])]
class BookingPayment extends Model
{
    protected function casts(): array
    {
        return [
            'booking_id' => 'integer',
            'expected_amount' => 'decimal:2',
            'transferred_amount' => 'decimal:2',
            'transfer_date' => 'date',
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
