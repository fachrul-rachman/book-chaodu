<?php

namespace App\Enums;

enum VirtualAccountStatus: string
{
    case Available = 'AVAILABLE';
    case Held = 'HELD';
    case Assigned = 'ASSIGNED';
}
