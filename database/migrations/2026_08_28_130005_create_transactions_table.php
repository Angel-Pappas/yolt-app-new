<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transactions — the core financial records. `type` drives which fields apply:
 * income/expense affect one wallet; a transfer moves money from `wallet_id` to
 * `to_wallet_id`. `net`/`vat_amount`/`withheld_amount` are non-negative summed
 * magnitudes (direction implied by `type`); the per-rate breakdown lives in the
 * line tables. `date` is when money moved; `invoice_date` drives VAT period
 * attribution. Shared company data; `user_id` is a created-by audit field.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->date('invoice_date');
            $table->string('description')->default('');
            $table->string('type'); // income | expense | transfer
            $table->decimal('net', 12, 2)->default(0);
            $table->decimal('vat_amount', 12, 2)->default(0);
            $table->decimal('withheld_amount', 12, 2)->default(0);
            $table->foreignId('entity_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('wallet_id')->constrained();
            $table->foreignId('to_wallet_id')->nullable()->constrained('wallets');
            $table->foreignId('vat_rate_id')->nullable()->constrained('vat_rates')->nullOnDelete();
            $table->boolean('is_reconciled')->default(false);
            $table->unsignedTinyInteger('invoice_month')->nullable();
            $table->boolean('invoice_not_required')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index('date');
            $table->index('type');
            $table->index('wallet_id');
            $table->index('entity_id');
            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
