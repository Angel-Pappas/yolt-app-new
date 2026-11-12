<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Three things a transaction can now carry:
 *  - `is_excluded`: switched off — it stays in every list but counts for nothing
 *    (balances, the projection, every tax). Recurrences and projects carry the same
 *    switch, which flips all their unreconciled rows.
 *  - `income_status`: a hand-set tag on an income (agreed → invoiced → paid, or
 *    dropped). Independent of reconciling. A dropped income counts for nothing.
 *  - `project_id`: the project this income is an installment of.
 *
 * Existing incomes start as "paid" when reconciled, else "agreed" (a one-time
 * backfill of the new column; nothing existing is rewritten).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->boolean('is_excluded')->default(false)->after('is_one_off');
            $table->string('income_status', 16)->nullable()->after('is_excluded');
            $table->foreignId('project_id')->nullable()->after('recurrence_id')->constrained()->nullOnDelete();
            $table->index('is_excluded');
            $table->index('income_status');
        });

        Schema::table('recurrences', function (Blueprint $table) {
            $table->boolean('is_excluded')->default(false)->after('active');
        });

        DB::table('transactions')->where('type', 'income')->where('is_reconciled', true)->update(['income_status' => 'paid']);
        DB::table('transactions')->where('type', 'income')->where('is_reconciled', false)->update(['income_status' => 'agreed']);
    }

    public function down(): void
    {
        Schema::table('recurrences', function (Blueprint $table) {
            $table->dropColumn('is_excluded');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
            $table->dropIndex(['is_excluded']);
            $table->dropIndex(['income_status']);
            $table->dropColumn(['is_excluded', 'income_status']);
        });
    }
};
