<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The dated amount timeline of a recurrence. Each entry is the amount(s) in force
 * for a span (`start_date` → optional `end_date`) — for a plain recurrence just the
 * `net`, for a payroll one the Net/FMY/EFKA set. The generator picks whichever entry
 * covers each occurrence's date, so past entries are history and the last is now/
 * future. Only the amount varies over time; everything else is on the recurrence.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurrence_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recurrence_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->decimal('net', 12, 2)->default(0);
            $table->decimal('fmy_amount', 12, 2)->nullable();
            $table->decimal('efka_employee_amount', 12, 2)->nullable();
            $table->decimal('efka_employer_amount', 12, 2)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index('recurrence_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurrence_entries');
    }
};
