<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'media_uuid',
    'scope',
    'media_type',
    'original_filename',
    'deleted_by',
    'deleted_at',
])]
class GalleryMediaDeletion extends Model
{
    protected function casts(): array
    {
        return [
            'deleted_by' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }
}
