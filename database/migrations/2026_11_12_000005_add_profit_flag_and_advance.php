<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * For the income tax estimate:
 *  - `categories.counts_for_profit`: whether a category's transactions are part of
 *    the company's profit (revenue or deductible cost). Off for money that only
 *    passes through — tax payments, deposits. Starts off for the categories named
 *    "Taxes" and "Deposit"; everything else starts on.
 *  - `income_tax_years.advance_amount`: the part of an entered year's tax that is the
 *    advance toward the next year — credited against the next year's estimate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('counts_for_profit')->default(true)->after('description');
        });
        DB::table('categories')->whereIn('name', ['Taxes', 'Deposit'])->update(['counts_for_profit' => false]);

        Schema::table('income_tax_years', function (Blueprint $table) {
            $table->decimal('advance_amount', 12, 2)->nullable()->after('total_tax');
        });
    }

    public function down(): void
    {
        Schema::table('income_tax_years', function (Blueprint $table) {
            $table->dropColumn('advance_amount');
        });
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('counts_for_profit');
        });
    }
};
