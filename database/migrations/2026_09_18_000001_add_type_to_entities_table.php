<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Classify entities by type. Every existing entity starts untyped (null) — the
 * "Cheese" bucket — to be assigned one of customer / supplier / contractor /
 * employee / state and migrated out. The type drives which entities the
 * transaction form offers (income → customers; expense → the rest) and, later,
 * how recurring transactions are generated. Kept a plain nullable string
 * (validated at the app layer), like `categories.type`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            $table->string('type')->nullable()->after('name');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
        });
    }
};
