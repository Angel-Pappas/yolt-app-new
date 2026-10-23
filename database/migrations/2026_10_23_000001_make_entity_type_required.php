<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every entity now has a type — the unclassified "Cheese" bucket is gone (all live
 * entities were classified by 2026-10-23). The column becomes NOT NULL so an
 * untyped entity can never reappear. It never rewrites data: if any untyped entity
 * still exists (even soft-deleted) the migration stops instead of guessing a type.
 */
return new class extends Migration
{
    public function up(): void
    {
        $untyped = DB::table('entities')->whereNull('type')->count();
        if ($untyped > 0) {
            throw new RuntimeException("{$untyped} entities have no type; classify them before making the type required.");
        }

        Schema::table('entities', function (Blueprint $table) {
            $table->string('type')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            $table->string('type')->nullable()->change();
        });
    }
};
