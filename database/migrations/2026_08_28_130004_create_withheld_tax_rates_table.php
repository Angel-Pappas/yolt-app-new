<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Withheld (withholding) tax rates — mirrors vat_rates. The reference list of
 * withholding percentages a transaction's withheld line can use.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withheld_tax_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->decimal('rate', 5, 2);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withheld_tax_rates');
    }
};
