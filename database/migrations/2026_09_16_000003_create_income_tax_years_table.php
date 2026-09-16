<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Income tax, entered per fiscal year (the first *stored* tax — every other tax is
 * computed live). A year records the taxable revenue, the total tax, the installment
 * window (first → last month) and the flat monthly installment amount. The ledger
 * generates one payment obligation per month in that window, each due on that month's
 * last working day. Manual for now; autofilled later once the app computes results.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('income_tax_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('year');
            $table->decimal('revenue_before_tax', 12, 2)->default(0);
            $table->decimal('total_tax', 12, 2)->default(0);
            $table->date('first_installment_month');
            $table->date('last_installment_month');
            $table->decimal('monthly_installment_amount', 12, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('income_tax_years');
    }
};
