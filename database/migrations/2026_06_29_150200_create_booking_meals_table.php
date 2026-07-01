<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_meals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('vegetarian_quantity');
            $table->unsignedInteger('non_vegetarian_quantity');
            $table->timestamps();
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('
                ALTER TABLE booking_meals
                ADD CONSTRAINT booking_meals_non_negative_check
                CHECK (
                    vegetarian_quantity >= 0
                    AND non_vegetarian_quantity >= 0
                )
            ');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_meals');
    }
};
