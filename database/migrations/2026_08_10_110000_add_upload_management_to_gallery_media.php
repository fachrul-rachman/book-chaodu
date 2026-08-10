<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gallery_media', function (Blueprint $table): void {
            $table->unsignedBigInteger('sort_order')->nullable()->after('caption')->index();
            $table->uuid('upload_token')->nullable()->unique()->after('size_bytes');
            $table->string('upload_mode', 20)->nullable()->after('upload_token');
            $table->text('upload_id')->nullable()->after('upload_mode');
            $table->timestamp('upload_expires_at')->nullable()->after('upload_id');
        });

        Schema::create('gallery_media_deletions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('media_uuid')->index();
            $table->string('scope', 20);
            $table->string('media_type', 20);
            $table->string('original_filename');
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('deleted_at');
            $table->timestamps();
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE gallery_media ADD CONSTRAINT gallery_media_upload_mode_check CHECK (upload_mode IS NULL OR upload_mode IN ('SINGLE', 'MULTIPART'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_media_deletions');

        Schema::table('gallery_media', function (Blueprint $table): void {
            $table->dropColumn(['sort_order', 'upload_token', 'upload_mode', 'upload_id', 'upload_expires_at']);
        });
    }
};
