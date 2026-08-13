<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->string('customer_phone', 20)->nullable()->change();
            $table->string('customer_email', 120)->nullable()->change();
            $table->unsignedInteger('attendee_count')->nullable()->change();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE bookings
                ADD CONSTRAINT bookings_required_customer_details_check
                CHECK (
                    referral_source = 'INTERNAL_PERUSAHAAN'
                    OR (
                        customer_phone IS NOT NULL
                        AND customer_email IS NOT NULL
                        AND attendee_count IS NOT NULL
                    )
                )
            SQL);
        }
    }

    public function down(): void
    {
        if (DB::table('bookings')->whereNull('customer_phone')->orWhereNull('customer_email')->orWhereNull('attendee_count')->exists()) {
            throw new RuntimeException('Rollback tidak aman: lengkapi atau hapus booking internal minimal terlebih dahulu.');
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE bookings DROP CONSTRAINT IF EXISTS bookings_required_customer_details_check');
        }

        Schema::table('bookings', function (Blueprint $table): void {
            $table->string('customer_phone', 20)->nullable(false)->change();
            $table->string('customer_email', 120)->nullable(false)->change();
            $table->unsignedInteger('attendee_count')->nullable(false)->change();
        });
    }
};
