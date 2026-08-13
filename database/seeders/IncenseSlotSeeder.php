<?php

namespace Database\Seeders;

use App\Services\SlotCapacityService;
use Illuminate\Database\Seeder;

class IncenseSlotSeeder extends Seeder
{
    public function run(): void
    {
        app(SlotCapacityService::class)->syncIncense();
    }
}
