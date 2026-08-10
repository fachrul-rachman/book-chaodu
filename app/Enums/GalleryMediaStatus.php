<?php

namespace App\Enums;

enum GalleryMediaStatus: string
{
    case Processing = 'PROCESSING';
    case Ready = 'READY';
    case Failed = 'FAILED';
    case Hidden = 'HIDDEN';
}
