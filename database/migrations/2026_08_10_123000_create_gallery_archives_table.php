<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gallery_archives', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->char('fingerprint', 64);
            $table->string('status', 20)->default('PENDING');
            $table->string('storage_disk', 50);
            $table->string('file_path', 512)->nullable()->unique();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['booking_id', 'fingerprint']);
            $table->index(['booking_id', 'status']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("
                ALTER TABLE gallery_archives
                ADD CONSTRAINT gallery_archives_status_check
                CHECK (status IN ('PENDING', 'PROCESSING', 'READY', 'FAILED', 'EXPIRED'))
            ");
            DB::statement('
                ALTER TABLE gallery_archives
                ADD CONSTRAINT gallery_archives_size_check
                CHECK (size_bytes IS NULL OR size_bytes >= 0)
            ');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_archives');
    }
};
