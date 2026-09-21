<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A single-row app settings store. `managed_from` is the floor for auto-generated
 * ("managed") transactions: nothing — recurrence occurrence or tax payment — is ever
 * created with a date before it. It marks where the app takes over from manually
 * recorded history, and stays fixed as time passes (null = no floor). `tax_wallet_id`
 * (added later) is the default wallet for generated tax payments.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->date('managed_from')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
