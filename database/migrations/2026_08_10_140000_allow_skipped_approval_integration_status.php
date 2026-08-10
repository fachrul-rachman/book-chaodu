<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<int, string> */
    private array $columns = [
        'qr_status',
        'drive_status',
        'notion_status',
        'approval_email_status',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->columns as $column) {
            DB::statement("ALTER TABLE approval_integrations DROP CONSTRAINT IF EXISTS approval_integrations_{$column}_check");
            DB::statement("ALTER TABLE approval_integrations ADD CONSTRAINT approval_integrations_{$column}_check CHECK ({$column} IN ('PENDING', 'PROCESSING', 'SUCCEEDED', 'FAILED', 'SKIPPED'))");
        }
    }

    public function down(): void
    {
        DB::table('approval_integrations')
            ->where('drive_status', 'SKIPPED')
            ->update(['drive_status' => 'PENDING']);
        DB::table('approval_integrations')
            ->where('notion_status', 'SKIPPED')
            ->update(['notion_status' => 'PENDING']);

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->columns as $column) {
            DB::statement("ALTER TABLE approval_integrations DROP CONSTRAINT IF EXISTS approval_integrations_{$column}_check");
            DB::statement("ALTER TABLE approval_integrations ADD CONSTRAINT approval_integrations_{$column}_check CHECK ({$column} IN ('PENDING', 'PROCESSING', 'SUCCEEDED', 'FAILED'))");
        }
    }
};
