<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A recurrence period now carries the same amount shape as a hand-entered
 * transaction: any number of amount lines, each with its own VAT rate and optional
 * withholding rate, interpreted by the period's own Net/Total `amount_mode`. The
 * typed amount is stored as-is (not converted to net) so every generated row
 * reconstructs exactly what was typed, via the same resolver the transaction form
 * uses. The single recurrence-level VAT/withholding rate is folded into each
 * existing period as one line, then dropped. Payroll periods keep using the
 * entry's net/FMY/EFKA columns and have no lines.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurrence_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recurrence_entry_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2)->default(0);
            $table->foreignId('vat_rate_id')->nullable()->constrained('vat_rates')->nullOnDelete();
            $table->foreignId('withheld_rate_id')->nullable()->constrained('withheld_tax_rates')->nullOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index('recurrence_entry_id');
        });

        Schema::table('recurrence_entries', function (Blueprint $table) {
            $table->string('amount_mode')->default('net'); // net | total
        });

        // Each existing non-payroll period becomes one line at the recurrence's rates.
        $recurrences = DB::table('recurrences')->get(['id', 'is_payroll', 'vat_rate_id', 'withheld_rate_id']);
        foreach ($recurrences as $r) {
            if ($r->is_payroll) {
                continue;
            }
            foreach (DB::table('recurrence_entries')->where('recurrence_id', $r->id)->get(['id', 'net']) as $e) {
                DB::table('recurrence_entry_lines')->insert([
                    'recurrence_entry_id' => $e->id,
                    'amount' => $e->net,
                    'vat_rate_id' => $r->vat_rate_id,
                    'withheld_rate_id' => $r->withheld_rate_id,
                    'position' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        Schema::table('recurrences', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vat_rate_id');
            $table->dropConstrainedForeignId('withheld_rate_id');
        });
    }

    public function down(): void
    {
        Schema::table('recurrences', function (Blueprint $table) {
            $table->foreignId('vat_rate_id')->nullable()->constrained('vat_rates')->nullOnDelete();
            $table->foreignId('withheld_rate_id')->nullable()->constrained('withheld_tax_rates')->nullOnDelete();
        });

        // Best effort: the first line's rates become the recurrence's rates again.
        foreach (DB::table('recurrence_entries')->get(['id', 'recurrence_id']) as $e) {
            $line = DB::table('recurrence_entry_lines')->where('recurrence_entry_id', $e->id)->orderBy('position')->first();
            if ($line !== null) {
                DB::table('recurrences')->where('id', $e->recurrence_id)->update([
                    'vat_rate_id' => $line->vat_rate_id,
                    'withheld_rate_id' => $line->withheld_rate_id,
                ]);
            }
        }

        Schema::table('recurrence_entries', function (Blueprint $table) {
            $table->dropColumn('amount_mode');
        });

        Schema::dropIfExists('recurrence_entry_lines');
    }
};
