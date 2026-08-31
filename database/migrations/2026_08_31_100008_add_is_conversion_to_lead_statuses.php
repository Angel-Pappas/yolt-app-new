<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks the flagged "Converted" lead status — the done-state the Convert-to-project
 * action sets, distinct from the manually-pickable "Project Agreed" stage. Keyed off
 * this flag (never the name) so a rename can't break the conversion logic. Only one
 * status is ever flagged; that single-true invariant is maintained by the seeder
 * (the flag is data-only — not editable through the status CRUD, which edits only
 * the name). Postgres would use a partial unique index here; MySQL has no partial
 * indexes, so the invariant is enforced by construction instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_statuses', function (Blueprint $table) {
            $table->boolean('is_conversion')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('lead_statuses', function (Blueprint $table) {
            $table->dropColumn('is_conversion');
        });
    }
};
