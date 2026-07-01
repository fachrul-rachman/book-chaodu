<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_payments', function (Blueprint $table): void {
            $table->string('virtual_account_bank_name', 120)->nullable()->after('proof_path');
            $table->string('virtual_account_number', 50)->nullable()->after('virtual_account_bank_name');
            $table->string('virtual_account_holder', 120)->nullable()->after('virtual_account_number');
        });
    }

    public function down(): void
    {
        Schema::table('booking_payments', function (Blueprint $table): void {
            $table->dropColumn([
                'virtual_account_bank_name',
                'virtual_account_number',
                'virtual_account_holder',
            ]);
        });
    }
};
