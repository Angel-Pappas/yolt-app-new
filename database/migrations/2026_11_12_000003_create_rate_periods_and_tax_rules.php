<?php

use App\Support\TaxRuleDefaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Date-sensitive configuration:
 *  - `rate_periods`: a dated change of a VAT or withholding rate ("from this date the
 *    rate is X%"). A rate's own `rate` column stays what applies before its first
 *    dated change.
 *  - `tax_rules`: a dated snapshot of every tax-law figure the app calculates with
 *    (social insurance, the payroll tax scale, company income tax, severance cap).
 *    The set in force on a date is the latest one starting on or before it.
 *
 * Starts with the 2025 and 2026 law (see TaxRuleDefaults for sources).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rate_periods', function (Blueprint $table) {
            $table->id();
            $table->string('rate_type', 16); // 'vat' | 'withheld'
            $table->unsignedBigInteger('rate_id');
            $table->date('valid_from');
            $table->decimal('rate', 5, 2);
            $table->timestamps();
            $table->unique(['rate_type', 'rate_id', 'valid_from']);
        });

        Schema::create('tax_rules', function (Blueprint $table) {
            $table->id();
            $table->date('valid_from')->unique();
            $table->json('data');
            $table->string('note')->nullable();
            $table->timestamps();
        });

        foreach (TaxRuleDefaults::sets() as $validFrom => $set) {
            DB::table('tax_rules')->insert([
                'valid_from' => $validFrom,
                'data' => json_encode($set['data']),
                'note' => $set['note'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rules');
        Schema::dropIfExists('rate_periods');
    }
};
