<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->timestamp('payment_expires_at')->nullable()->index();
        });

        $expiryHours = max(1, (int) config('phase3.payment_link_expiry_hours', 24));

        DB::table('bookings')
            ->select(['id', 'created_at'])
            ->whereNull('payment_expires_at')
            ->orderBy('id')
            ->chunkById(100, function ($bookings) use ($expiryHours): void {
                foreach ($bookings as $booking) {
                    if ($booking->created_at === null) {
                        continue;
                    }

                    DB::table('bookings')
                        ->where('id', $booking->id)
                        ->update([
                            'payment_expires_at' => Carbon::parse($booking->created_at)
                                ->addHours($expiryHours),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn('payment_expires_at');
        });
    }
};
