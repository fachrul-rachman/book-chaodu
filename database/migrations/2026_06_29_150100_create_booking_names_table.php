<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_names', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('category', 20);
            $table->unsignedTinyInteger('position');
            $table->string('indonesian_name', 120)->nullable();
            $table->string('mandarin_name', 120)->nullable();
            $table->string('mandarin_source_image_path')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['booking_id', 'category', 'position']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("
                ALTER TABLE booking_names
                ADD CONSTRAINT booking_names_category_check
                CHECK (category IN ('DECEASED', 'INCENSE'))
            ");

            DB::statement('
                ALTER TABLE booking_names
                ADD CONSTRAINT booking_names_position_check
                CHECK (position IN (1, 2))
            ');

            DB::statement('
                ALTER TABLE booking_names
                ADD CONSTRAINT booking_names_name_present_check
                CHECK (
                    indonesian_name IS NOT NULL
                    OR mandarin_name IS NOT NULL
                )
            ');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_names');
    }
};
