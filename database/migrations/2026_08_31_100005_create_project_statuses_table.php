<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Project statuses — the project lifecycle stages (Agreed, Scoping, …), a separate
 * pipeline from lead statuses. Business/CRM data, gated by CRM access; `position`
 * drives display order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->integer('position')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_statuses');
    }
};
