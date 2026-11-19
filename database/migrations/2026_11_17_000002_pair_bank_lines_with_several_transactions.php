<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One bank line can be several rows of the app (2026-11-17): Alpha books a credit-line
 * repayment and its cost as one −25.003,78 line, while the app has the €25.000
 * transfer and the €3,78 fee. So the pairing moves from the line to the rows —
 * `transactions.bank_line_id` — and a line has many rows. Existing pairs are carried
 * over (only the test copy has any).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('bank_line_id')->nullable()->after('bank_position')->constrained()->nullOnDelete();
        });

        DB::table('bank_lines')->whereNotNull('transaction_id')->orderBy('id')
            ->each(fn (object $line) => DB::table('transactions')->where('id', $line->transaction_id)->update(['bank_line_id' => $line->id]));

        Schema::table('bank_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('bank_lines', function (Blueprint $table) {
            $table->foreignId('transaction_id')->nullable()->after('wallet_id')->constrained()->nullOnDelete();
        });

        // A line keeps one of its rows (the first); the others are let go.
        DB::table('transactions')->whereNotNull('bank_line_id')->orderByDesc('id')
            ->each(fn (object $t) => DB::table('bank_lines')->where('id', $t->bank_line_id)->update(['transaction_id' => $t->id]));

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_line_id');
        });
    }
};
