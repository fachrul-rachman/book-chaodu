<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gallery_media', function (Blueprint $table): void {
            $table->unsignedBigInteger('source_prayer_paper_id')->nullable()->unique()->after('booking_id');
        });
    }

    public function down(): void
    {
        Schema::table('gallery_media', function (Blueprint $table): void {
            $table->dropUnique('gallery_media_source_prayer_paper_id_unique');
            $table->dropColumn('source_prayer_paper_id');
        });
    }
};
