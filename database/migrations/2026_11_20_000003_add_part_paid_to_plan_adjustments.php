<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A computed plan item (recurrence occurrence, tax payment) is settled by the real
 * row that paid it, whatever the exact amount — a bill or a payslip differs a little
 * from the estimate. Only a payment split on purpose (Bank import: paid in part, the
 * rest due on a date) keeps the rest open; this marks it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_adjustments', function (Blueprint $table): void {
            $table->boolean('part_paid')->default(false)->after('cancelled');
        });
    }

    public function down(): void
    {
        Schema::table('plan_adjustments', fn (Blueprint $table) => $table->dropColumn('part_paid'));
    }
};
