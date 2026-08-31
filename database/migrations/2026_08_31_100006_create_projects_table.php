<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Projects — a won lead graduated into its own tracked entity, kept separate from
 * the chasing pipeline. Business/CRM data, gated by CRM access. Holds project info
 * only — client/contact details stay on the linked lead (`lead_id`, set null if the
 * lead is deleted). `sort_order` is the auto "No." (max+1, never reused). `value` is
 * a manual amount for now (a later phase sums it from deliverables);
 * `estimated_months` is a rough pre-dates estimate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('sort_order')->default(0);
            $table->string('name');
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('status_id')->nullable()->constrained('project_statuses')->nullOnDelete();
            $table->text('description')->nullable();
            $table->decimal('value', 12, 2)->nullable();
            $table->integer('estimated_months')->nullable();
            $table->text('next_step')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
