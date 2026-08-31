<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lead actions — the History log on a lead (calls, emails, meetings). Business/CRM
 * data, gated by CRM access. `action_date` is the editable date the action
 * happened (defaults to today), separate from the `created_at` audit stamp;
 * `author_name` is denormalized (a colleague's name can't be joined under the
 * CRM's shared-read model). Soft-deletes, like everything in the app.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->date('action_date');
            $table->text('body');
            $table->string('author_name')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('lead_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_actions');
    }
};
