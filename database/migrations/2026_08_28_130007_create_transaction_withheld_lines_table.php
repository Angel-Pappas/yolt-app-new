<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The per-rate withholding breakdown behind a transaction's summed
 * withheld_amount — the exact parallel of transaction_vat_lines.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_withheld_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->decimal('net', 12, 2)->default(0);
            $table->foreignId('withheld_rate_id')->nullable()->constrained('withheld_tax_rates')->nullOnDelete();
            $table->decimal('withheld_amount', 12, 2)->default(0);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_withheld_lines');
    }
};
