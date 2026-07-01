<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('prayer_paper_status', 20)->default('PENDING')->after('rejected_by');
            $table->text('prayer_paper_error')->nullable()->after('prayer_paper_status');
            $table->timestamp('latest_prayer_paper_generated_at')->nullable()->after('prayer_paper_error');
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("
                ALTER TABLE bookings
                ADD CONSTRAINT bookings_prayer_paper_status_check
                CHECK (prayer_paper_status IN ('PENDING', 'PROCESSING', 'READY', 'FAILED'))
            ");
        }
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'prayer_paper_status',
                'prayer_paper_error',
                'latest_prayer_paper_generated_at',
            ]);
        });
    }
};
