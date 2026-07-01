<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prayer_papers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('type', 1);
            $table->string('file_path')->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->string('status', 20)->default('PENDING');
            $table->text('error_message')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
            $table->unique(['booking_id', 'type']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("
                ALTER TABLE prayer_papers
                ADD CONSTRAINT prayer_papers_type_check
                CHECK (type IN ('A', 'B'))
            ");

            DB::statement("
                ALTER TABLE prayer_papers
                ADD CONSTRAINT prayer_papers_status_check
                CHECK (status IN ('PENDING', 'PROCESSING', 'READY', 'FAILED'))
            ");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('prayer_papers');
    }
};
