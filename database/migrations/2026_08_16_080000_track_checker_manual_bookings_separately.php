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
            $table->boolean('is_checker_manual')->default(false)->after('agent_name');
        });

        DB::table('bookings')
            ->where('referral_source', 'CHECKER_MANUAL')
            ->update(['is_checker_manual' => true]);

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
                        OR is_checker_manual = TRUE
                    )
                )
            )
        SQL);
    }

    public function down(): void
    {
        if (DB::table('bookings')
            ->where('is_checker_manual', true)
            ->where('referral_source', '<>', 'CHECKER_MANUAL')
            ->exists()) {
            throw new RuntimeException('Rollback tidak aman: booking manual dengan sumber Site/Agent masih tersedia.');
        }

        if (DB::getDriverName() === 'pgsql') {
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

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn('is_checker_manual');
        });
    }
};
