<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De minimis aid — every state grant the company was given under the de minimis
 * rules, kept so the "Πίνακας Επιχορηγήσεων de minimis" that grant applications ask
 * for can be filled straight from the app. One row per approval decision (or
 * contract), with the columns of that official table. Shared company data with a
 * created-by audit `user_id`; soft-deleted like every other list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('de_minimis_aids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('programme');
            $table->string('decision_number');
            $table->date('decision_date');
            $table->decimal('approved_amount', 12, 2);
            $table->decimal('paid_amount', 12, 2)->nullable();
            $table->date('last_payment_date')->nullable();
            $table->string('beneficiary_name');
            $table->string('beneficiary_vat_number');
            $table->timestamps();
            $table->softDeletes();

            $table->index('decision_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('de_minimis_aids');
    }
};
