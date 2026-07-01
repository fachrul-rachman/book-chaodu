<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('virtual_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('package_code', 20);
            $table->string('account_number', 50);
            $table->string('status', 20)->default('AVAILABLE');
            $table->string('hold_reference', 120)->nullable();
            $table->timestamp('hold_expires_at')->nullable();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['package_code', 'account_number']);
            $table->unique('booking_id');
            $table->index(['package_code', 'status', 'id']);
            $table->index(['hold_reference', 'status']);
            $table->index('hold_expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('virtual_accounts');
    }
};
