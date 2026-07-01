<?php

namespace App\Enums;

enum SlotStatus: string
{
    case Available = 'AVAILABLE';
    case Reserved = 'RESERVED';
    case Assigned = 'ASSIGNED';
}
