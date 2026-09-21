<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Provenance for auto-generated ("managed") transactions. A transaction is just a
 * transaction, but a managed one is kept in sync by its source and frozen only by
 * `is_reconciled`:
 *
 *  - `source`      — 'recurrence' | 'tax' (null = a manual transaction).
 *  - `recurrence_id` — the recurrence that generated it (null for tax/manual). On a
 *    recurrence delete the FK nulls, so a reconciled historical row survives.
 *  - `managed_key` — the sync slot it fills: `recurrence:{id}:{Y-m-d}` or
 *    `tax:{tax}:{Y-m}`. Sync finds/updates/deletes the row by this key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('source')->nullable()->after('is_reconciled');
            $table->foreignId('recurrence_id')->nullable()->after('source')
                ->constrained()->nullOnDelete();
            $table->string('managed_key')->nullable()->after('recurrence_id');
            $table->index('managed_key');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['managed_key']);
            $table->dropConstrainedForeignId('recurrence_id');
            $table->dropColumn(['source', 'managed_key']);
        });
    }
};
