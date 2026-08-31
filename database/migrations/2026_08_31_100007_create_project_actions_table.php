<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Project actions — the History log on a project, the exact parallel of
 * lead_actions. Business/CRM data, gated by CRM access. `action_date` is the
 * editable date the action happened (defaults today); `author_name` is
 * denormalized. Soft-deletes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->date('action_date');
            $table->text('body');
            $table->string('author_name')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_actions');
    }
};
