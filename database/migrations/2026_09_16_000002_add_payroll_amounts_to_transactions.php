<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll tax amounts on transactions — manual capture until real payroll exists.
 * A transaction filed under the "Payroll" category carries FMY and EFKA
 * (employee + employer) amounts. FMY and the employee EFKA are withheld from the
 * employee (they lower the cash paid out — the "To Pay" = net − fmy − efka_employee),
 * while the employer EFKA is a pure liability that never touches the wallet. All
 * three feed the FMY / EFKA tax buckets.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('fmy_amount', 12, 2)->nullable()->after('withheld_amount');
            $table->decimal('efka_employee_amount', 12, 2)->nullable()->after('fmy_amount');
            $table->decimal('efka_employer_amount', 12, 2)->nullable()->after('efka_employee_amount');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['fmy_amount', 'efka_employee_amount', 'efka_employer_amount']);
        });
    }
};
