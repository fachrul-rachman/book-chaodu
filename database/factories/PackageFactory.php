<?php

namespace Database\Factories;

use App\Enums\PackageCode;
use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Package>
 */
class PackageFactory extends Factory
{
    protected $model = Package::class;

    public function definition(): array
    {
        return [
            'code' => fake()->randomElement([
                PackageCode::Prayer,
                PackageCode::Incense,
                PackageCode::Combo,
            ]),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'price' => fake()->numberBetween(100000, 2000000),
            'image_path' => null,
            'is_active' => false,
            'meal_quota' => fake()->randomElement([2, 4]),
            'requires_table' => fake()->boolean(),
            'requires_incense' => fake()->boolean(),
        ];
    }
}
