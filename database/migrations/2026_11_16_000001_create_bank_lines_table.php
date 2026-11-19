<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bank statement import: each imported statement line (kept so a line is never
 * imported twice and so its link to the transaction it became is on record), the
 * bank's own order within a day on transactions, and per wallet where its bank fees
 * go plus the balance the last statement showed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->foreignId('fee_entity_id')->nullable()->after('starting_balance')->constrained('entities')->nullOnDelete();
            $table->foreignId('fee_category_id')->nullable()->after('fee_entity_id')->constrained('categories')->nullOnDelete();
            $table->decimal('statement_balance', 12, 2)->nullable()->after('fee_category_id');
            $table->date('statement_date')->nullable()->after('statement_balance');
        });

        Schema::table('transactions', function (Blueprint $table) {
            // Order within the day as on the bank statement (1 = first); null for rows
            // no statement line has confirmed yet.
            $table->unsignedInteger('bank_position')->nullable()->after('date');
        });

        Schema::create('bank_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->unsignedInteger('position');
            $table->string('text');
            // Signed: money in is positive, money out negative.
            $table->decimal('amount', 12, 2);
            $table->string('reference', 64)->default('');
            $table->string('fingerprint', 64);
            $table->boolean('is_dismissed')->default(false);
            $table->timestamps();

            $table->unique(['wallet_id', 'fingerprint']);
            $table->index(['wallet_id', 'date']);
        });

        // The main bank wallet's fees already go to the bank (entity) under Bank
        // Expenses — set that up where all three exist under those names.
        $wallet = DB::table('wallets')->where('name', 'Alpha Bank')->whereNull('deleted_at')->value('id');
        $entity = DB::table('entities')->where('name', 'Alpha Bank')->whereNull('deleted_at')->value('id');
        $category = DB::table('categories')->where('name', 'Bank Expenses')->where('type', 'expense')->whereNull('deleted_at')->value('id');
        if ($wallet !== null && $entity !== null && $category !== null) {
            DB::table('wallets')->where('id', $wallet)->update(['fee_entity_id' => $entity, 'fee_category_id' => $category]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_lines');
        Schema::table('transactions', fn (Blueprint $table) => $table->dropColumn('bank_position'));
        Schema::table('wallets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fee_entity_id');
            $table->dropConstrainedForeignId('fee_category_id');
            $table->dropColumn(['statement_balance', 'statement_date']);
        });
    }
};
