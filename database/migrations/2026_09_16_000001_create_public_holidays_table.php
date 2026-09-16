<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Public holidays — the dates (per year) that, together with weekends, are excluded
 * when computing a tax's "last working day of the month" payment date. Shared company
 * data with a created-by audit `user_id`; soft-deleted like every other lookup list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->string('name')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_holidays');
    }
};
