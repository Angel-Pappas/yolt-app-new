<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The default wallet for generated tax payments. When set, TaxSync files every
 * (unreconciled) tax transaction against it; null falls back to the first wallet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->foreignId('tax_wallet_id')->nullable()->after('managed_from')
                ->constrained('wallets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tax_wallet_id');
        });
    }
};
