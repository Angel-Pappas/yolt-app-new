<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wallets — where money is held (bank accounts, cash, etc.). Shared company data
 * (any finance user sees all of them); `user_id` is a created-by audit field, not
 * an ownership gate. `starting_balance` is the balance before the app started
 * tracking; the current balance is derived from it plus transactions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->decimal('starting_balance', 12, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
