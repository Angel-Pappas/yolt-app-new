<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campaign-only lead fields, shown when a lead's origin is "Campaign": the ad
 * platform and the "we are" / "we want" campaign notes. Carried over from the old
 * app (which stored these), so no data is lost at migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->enum('campaign_platform', ['facebook', 'instagram'])->nullable();
            $table->text('campaign_we_are')->nullable();
            $table->text('campaign_we_want')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['campaign_platform', 'campaign_we_are', 'campaign_we_want']);
        });
    }
};
