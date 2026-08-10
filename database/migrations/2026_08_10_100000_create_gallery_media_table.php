<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gallery_media', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('scope', 20);
            $table->foreignId('booking_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('media_type', 20);
            $table->string('status', 20)->default('PROCESSING');
            $table->string('storage_disk', 50);
            $table->string('original_path', 512)->unique();
            $table->string('preview_path', 512)->nullable()->unique();
            $table->string('thumbnail_path', 512)->nullable()->unique();
            $table->string('original_filename');
            $table->string('stored_filename');
            $table->string('mime_type', 120);
            $table->string('extension', 16);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->decimal('duration_seconds', 12, 3)->nullable();
            $table->text('caption')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['scope', 'status', 'published_at']);
            $table->index(['booking_id', 'status', 'published_at']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("
                ALTER TABLE gallery_media
                ADD CONSTRAINT gallery_media_scope_check
                CHECK (scope IN ('GLOBAL', 'BOOKING'))
            ");

            DB::statement("
                ALTER TABLE gallery_media
                ADD CONSTRAINT gallery_media_type_check
                CHECK (media_type IN ('IMAGE', 'VIDEO'))
            ");

            DB::statement("
                ALTER TABLE gallery_media
                ADD CONSTRAINT gallery_media_status_check
                CHECK (status IN ('PROCESSING', 'READY', 'FAILED', 'HIDDEN'))
            ");

            DB::statement("
                ALTER TABLE gallery_media
                ADD CONSTRAINT gallery_media_booking_scope_check
                CHECK (
                    (scope = 'GLOBAL' AND booking_id IS NULL)
                    OR (scope = 'BOOKING' AND booking_id IS NOT NULL)
                )
            ");

            DB::statement('
                ALTER TABLE gallery_media
                ADD CONSTRAINT gallery_media_dimensions_check
                CHECK (
                    size_bytes >= 0
                    AND
                    (width IS NULL OR width > 0)
                    AND (height IS NULL OR height > 0)
                    AND (duration_seconds IS NULL OR duration_seconds >= 0)
                )
            ');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_media');
    }
};
