<?php

namespace App\Enums;

enum GalleryArchiveStatus: string
{
    case Pending = 'PENDING';
    case Processing = 'PROCESSING';
    case Ready = 'READY';
    case Failed = 'FAILED';
    case Expired = 'EXPIRED';
}
