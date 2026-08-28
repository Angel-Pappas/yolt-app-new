<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Company-wide access control lives on the user row (mirrors the original app's
 * `profiles` flags): three independent access grants plus an active switch. New
 * users get no area access and are active by default — access is granted by an
 * admin (the invite flow, built later).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('password');
            $table->boolean('can_access_finance')->default(false)->after('is_admin');
            $table->boolean('can_access_crm')->default(false)->after('can_access_finance');
            $table->boolean('is_active')->default(true)->after('can_access_crm');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'is_admin',
                'can_access_finance',
                'can_access_crm',
                'is_active',
            ]);
        });
    }
};
