<?php

use App\Enums\SlotStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('table_slots', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('row_code', 2);
            $table->unsignedSmallInteger('number');
            $table->unsignedSmallInteger('allocation_order')->unique();
            $table->string('status', 20)->default(SlotStatus::Available->value);
            $table->unsignedBigInteger('booking_id')->nullable()->unique();
            $table->timestamps();

            $table->index(['status', 'allocation_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_slots');
    }
};
