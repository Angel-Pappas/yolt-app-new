<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The per-rate VAT breakdown behind a transaction's summed vat_amount. A
 * transaction usually has one line, occasionally more (one invoice mixing VAT
 * rates). Cascade-deletes with its transaction (a rare hard delete takes its
 * lines with it).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_vat_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->decimal('net', 12, 2)->default(0);
            $table->foreignId('vat_rate_id')->nullable()->constrained('vat_rates')->nullOnDelete();
            $table->decimal('vat_amount', 12, 2)->default(0);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_vat_lines');
    }
};
