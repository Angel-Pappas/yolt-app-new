<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Company-wide access control lives on the user row: an admin flag plus an active
 * switch. New users are active by default. (Two area grants — `can_access_finance`
 * and `can_access_crm` — also lived here originally; both areas were folded away in
 * 2026-09 and later migrations drop those columns.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('password');
            $table->boolean('is_active')->default(true)->after('is_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_admin', 'is_active']);
        });
    }
};
