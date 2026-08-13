<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE bookings DROP CONSTRAINT IF EXISTS bookings_required_customer_details_check');
        DB::statement(<<<'SQL'
            ALTER TABLE bookings
            ADD CONSTRAINT bookings_required_customer_details_check
            CHECK (
                referral_source = 'INTERNAL_PERUSAHAAN'
                OR (
                    customer_phone IS NOT NULL
                    AND customer_email IS NOT NULL
                    AND (
                        attendee_count IS NOT NULL
                        OR referral_source = 'CHECKER_MANUAL'
                    )
                )
            )
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        if (DB::table('bookings')
            ->where('referral_source', 'CHECKER_MANUAL')
            ->whereNull('attendee_count')
            ->exists()) {
            throw new RuntimeException('Rollback tidak aman: booking manual Checker masih memiliki jumlah hadir kosong.');
        }

        DB::statement('ALTER TABLE bookings DROP CONSTRAINT IF EXISTS bookings_required_customer_details_check');
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
};
