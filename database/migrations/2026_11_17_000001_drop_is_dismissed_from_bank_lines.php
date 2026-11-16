<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bank import became a side-by-side view of the bank and the app (2026-11-17): every
 * bank line is either paired with a row or missing from the app, so "set aside" is
 * gone. Only the test copy ever had lines; none were set aside.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_lines', function (Blueprint $table) {
            $table->dropColumn('is_dismissed');
        });
    }

    public function down(): void
    {
        Schema::table('bank_lines', function (Blueprint $table) {
            $table->boolean('is_dismissed')->default(false)->after('fingerprint');
        });
    }
};
