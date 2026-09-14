<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remove the Business/CRM area entirely. It has moved to a separate app, so its
 * tables and their data are dropped here, along with the `can_access_crm` access
 * flag on users. The Finance area is untouched.
 *
 * Child tables are dropped before their parents to satisfy foreign keys.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'project_actions',
            'projects',
            'project_statuses',
            'lead_actions',
            'lead_contacts',
            'leads',
            'lead_statuses',
            'lead_origins',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        if (Schema::hasColumn('users', 'can_access_crm')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('can_access_crm');
            });
        }
    }

    /**
     * The CRM tables and their data are gone for good; only the access flag is
     * restored on rollback (so the schema round-trips), not the dropped tables.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('users', 'can_access_crm')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->boolean('can_access_crm')->default(false)->after('can_access_finance');
            });
        }
    }
};
