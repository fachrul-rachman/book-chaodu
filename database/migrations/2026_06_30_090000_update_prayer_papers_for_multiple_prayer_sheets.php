<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prayer_papers', function (Blueprint $table) {
            $table->unsignedInteger('sequence')->default(1)->after('type');
        });

        DB::table('prayer_papers')->update([
            'sequence' => 1,
        ]);

        Schema::table('prayer_papers', function (Blueprint $table) {
            $table->dropUnique('prayer_papers_booking_id_type_unique');
            $table->unique(['booking_id', 'type', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::table('prayer_papers', function (Blueprint $table) {
            $table->dropUnique('prayer_papers_booking_id_type_sequence_unique');
            $table->dropColumn('sequence');
            $table->unique(['booking_id', 'type']);
        });
    }
};
