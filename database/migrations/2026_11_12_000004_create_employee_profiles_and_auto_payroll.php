<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the payroll, leave and severance calculations need to know about an employee
 * (one row per employee entity), and a per-period switch on a payroll recurrence:
 * `auto_payroll` = FMY and EFKA are calculated from the gross by the tax rules in
 * force on each pay day, instead of typed. Existing periods keep their typed figures.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->unique()->constrained()->cascadeOnDelete();
            $table->date('birth_date')->nullable();
            $table->unsignedTinyInteger('children')->default(0);
            $table->date('hire_date')->nullable();
            $table->date('termination_date')->nullable();
            // Years already worked for other employers (counts toward leave days).
            $table->decimal('prior_service_years', 4, 1)->default(0);
            $table->unsignedTinyInteger('work_days_per_week')->default(5);
            // Set to override the leave days the law gives.
            $table->unsignedTinyInteger('leave_days_override')->nullable();
            $table->timestamps();
        });

        Schema::table('recurrence_entries', function (Blueprint $table) {
            $table->boolean('auto_payroll')->default(false)->after('efka_employer_amount');
        });
    }

    public function down(): void
    {
        Schema::table('recurrence_entries', function (Blueprint $table) {
            $table->dropColumn('auto_payroll');
        });
        Schema::dropIfExists('employee_profiles');
    }
};
