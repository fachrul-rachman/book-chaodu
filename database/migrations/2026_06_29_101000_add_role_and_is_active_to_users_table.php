<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'role')) {
                $table->string('role', 20)->default(UserRole::Admin->value)->after('email');
            }

            if (! Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('role');
            }
        });

        if (Schema::hasColumn('users', 'role')) {
            DB::table('users')
                ->whereNull('role')
                ->update(['role' => UserRole::Admin->value]);
        }

        if (Schema::hasColumn('users', 'is_active')) {
            DB::table('users')
                ->whereNull('is_active')
                ->update(['is_active' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = [];

            if (Schema::hasColumn('users', 'role')) {
                $columns[] = 'role';
            }

            if (Schema::hasColumn('users', 'is_active')) {
                $columns[] = 'is_active';
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
