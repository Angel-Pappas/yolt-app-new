<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Company-wide access control lives on the user row (mirrors the original app's
 * `profiles` flags): the Finance access grant plus an active switch. New users get
 * no access and are active by default — access is granted by an admin (the invite
 * flow, built later). (A `can_access_crm` grant also lived here originally; the
 * Business/CRM area was removed 2026-09-14, and a later migration drops it.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('password');
            $table->boolean('can_access_finance')->default(false)->after('is_admin');
            $table->boolean('is_active')->default(true)->after('can_access_finance');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'is_admin',
                'can_access_finance',
                'is_active',
            ]);
        });
    }
};
