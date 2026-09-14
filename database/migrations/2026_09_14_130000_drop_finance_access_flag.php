<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the `can_access_finance` grant. With the Business/CRM area gone, Finance is
 * the whole app, so every active user sees everything — the per-user finance grant
 * is redundant. Access control is now just `is_admin` + `is_active`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'can_access_finance')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('can_access_finance');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'can_access_finance')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->boolean('can_access_finance')->default(false)->after('is_admin');
            });
        }
    }
};
