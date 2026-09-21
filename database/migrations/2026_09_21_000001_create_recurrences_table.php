<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A recurring income/expense defined on an entity. It carries the fixed template
 * (category, wallet, VAT/withholding rate, or the payroll shape) plus the cadence
 * (`interval_count` × `interval_unit`, on a chosen `day_of_month` for month/year)
 * and window (`start_date` → optional `end_date`). The amount that varies over time
 * lives in `recurrence_entries`; the generator turns all this into real
 * transactions across a rolling 24-month horizon.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // income | expense
            $table->string('description')->default('');
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('wallet_id')->constrained();
            $table->foreignId('vat_rate_id')->nullable()->constrained('vat_rates')->nullOnDelete();
            $table->foreignId('withheld_rate_id')->nullable()->constrained('withheld_tax_rates')->nullOnDelete();
            $table->boolean('is_payroll')->default(false);
            $table->unsignedSmallInteger('interval_count'); // 1..12
            $table->string('interval_unit'); // week | month | year
            $table->unsignedTinyInteger('day_of_month')->nullable(); // month/year cadence
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('entity_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurrences');
    }
};
