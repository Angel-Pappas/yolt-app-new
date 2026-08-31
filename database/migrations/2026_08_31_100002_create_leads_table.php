<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Leads — the CRM's chasing pipeline. Business/CRM data, gated by CRM access;
 * `user_id` is a created-by audit field. `sort_order` is an auto-assigned display
 * number ("No.") assigned at max+1 on save and never reused (soft-deletes keep
 * their number). The main contact lives in `contact_*` columns on the lead itself;
 * additional contacts and the activity log come in later slices.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('sort_order')->default(0);
            $table->string('name');
            $table->foreignId('origin_id')->nullable()->constrained('lead_origins')->nullOnDelete();
            $table->foreignId('status_id')->nullable()->constrained('lead_statuses')->nullOnDelete();
            $table->string('website')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_position')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_landline')->nullable();
            $table->text('description')->nullable();
            $table->text('next_step')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
