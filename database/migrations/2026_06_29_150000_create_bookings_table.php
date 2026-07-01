<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_number', 32)->unique();
            $table->string('idempotency_key', 120)->unique();
            $table->foreignId('package_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('package_code_snapshot', 20);
            $table->string('package_name_snapshot', 120);
            $table->decimal('package_price_snapshot', 12, 2);
            $table->string('customer_name', 120);
            $table->string('customer_phone', 20);
            $table->string('customer_email', 120);
            $table->unsignedInteger('attendee_count');
            $table->string('referral_source', 30);
            $table->string('agent_name', 120)->nullable();
            $table->string('status', 20)->default('PENDING');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("
                ALTER TABLE bookings
                ADD CONSTRAINT bookings_status_check
                CHECK (status IN ('PENDING', 'APPROVED', 'REJECTED'))
            ");

            DB::statement("
                ALTER TABLE bookings
                ADD CONSTRAINT bookings_agent_name_check
                CHECK (
                    (referral_source = 'AGENT' AND agent_name IS NOT NULL)
                    OR (referral_source <> 'AGENT')
                )
            ");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
