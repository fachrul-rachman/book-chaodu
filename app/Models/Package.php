<?php

namespace App\Models;

use App\Enums\PackageCode;
use Database\Factories\PackageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property PackageCode $code
 * @property string $name
 * @property string|null $description
 * @property string|null $price
 * @property string|null $image_path
 * @property bool $is_active
 * @property int $meal_quota
 * @property bool $requires_table
 * @property bool $requires_incense
 */
#[Fillable([
    'code',
    'name',
    'description',
    'price',
    'image_path',
    'is_active',
    'meal_quota',
    'requires_table',
    'requires_incense',
])]
class Package extends Model
{
    /** @use HasFactory<PackageFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'code' => PackageCode::class,
            'price' => 'decimal:2',
            'is_active' => 'boolean',
            'meal_quota' => 'integer',
            'requires_table' => 'boolean',
            'requires_incense' => 'boolean',
        ];
    }
}
