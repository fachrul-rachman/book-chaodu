<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('qr_status', 20)->default('PENDING');
            $table->string('qr_token_hash', 64)->nullable()->unique();
            $table->text('qr_token_encrypted')->nullable();
            $table->string('qr_image_path')->nullable();
            $table->text('qr_error')->nullable();
            $table->string('drive_status', 20)->default('PENDING');
            $table->string('drive_external_id')->nullable()->unique();
            $table->string('drive_url')->nullable();
            $table->text('drive_error')->nullable();
            $table->string('notion_status', 20)->default('PENDING');
            $table->string('notion_external_id')->nullable()->unique();
            $table->string('notion_url')->nullable();
            $table->text('notion_error')->nullable();
            $table->string('approval_email_status', 20)->default('PENDING');
            $table->timestamp('approval_email_sent_at')->nullable();
            $table->text('approval_email_error')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("
                ALTER TABLE approval_integrations
                ADD CONSTRAINT approval_integrations_qr_status_check
                CHECK (qr_status IN ('PENDING', 'PROCESSING', 'SUCCEEDED', 'FAILED'))
            ");

            DB::statement("
                ALTER TABLE approval_integrations
                ADD CONSTRAINT approval_integrations_drive_status_check
                CHECK (drive_status IN ('PENDING', 'PROCESSING', 'SUCCEEDED', 'FAILED'))
            ");

            DB::statement("
                ALTER TABLE approval_integrations
                ADD CONSTRAINT approval_integrations_notion_status_check
                CHECK (notion_status IN ('PENDING', 'PROCESSING', 'SUCCEEDED', 'FAILED'))
            ");

            DB::statement("
                ALTER TABLE approval_integrations
                ADD CONSTRAINT approval_integrations_approval_email_status_check
                CHECK (approval_email_status IN ('PENDING', 'PROCESSING', 'SUCCEEDED', 'FAILED'))
            ");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_integrations');
    }
};
