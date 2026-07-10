<?php

namespace App\Enums;

enum BookingStatus: string
{
    case AwaitingPayment = 'AWAITING_PAYMENT';
    case Pending = 'PENDING';
    case Approved = 'APPROVED';
    case Rejected = 'REJECTED';
    case Expired = 'EXPIRED';
}
