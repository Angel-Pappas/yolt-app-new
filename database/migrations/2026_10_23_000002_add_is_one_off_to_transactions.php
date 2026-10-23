<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tag a transaction as a one-off expense, so Finance ▸ One-offs can list them. Set
 * when a transaction is added from that page; every existing row starts untagged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->boolean('is_one_off')->default(false)->after('is_reconciled');
            $table->index('is_one_off');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['is_one_off']);
            $table->dropColumn('is_one_off');
        });
    }
};
