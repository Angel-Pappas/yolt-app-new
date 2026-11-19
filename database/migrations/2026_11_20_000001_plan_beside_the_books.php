<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The plan beside the books (Angel, 2026-11-19): Transactions holds only what
 * happened; the future is a plan (recurrences and taxes computed live, one-offs and
 * customer payment lines stored as **planned** rows of the same table, hidden from
 * every real query). Plus concluded dates per wallet, draft statement uploads,
 * project deliverables and customers' recurring income. Only adds — nothing existing
 * is changed here (the data step is the next migration).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            // A planned row: a one-off or a customer payment line still expected.
            $table->boolean('is_planned')->default(false)->after('is_one_off');
            // A payment line's invoice was issued (its invoice date stays put).
            $table->boolean('is_issued')->default(false)->after('is_planned');
            // The invoice date no longer follows the payment date (part-paid).
            $table->boolean('invoice_pinned')->default(false)->after('is_issued');
            // A payment line edited by hand (its recurring income leaves it alone).
            $table->boolean('is_edited')->default(false)->after('invoice_pinned');
            $table->string('recurring_slot', 10)->nullable()->after('managed_key');
            $table->index('is_planned');
        });

        Schema::create('recurring_incomes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('entity_id')->constrained();
            $table->foreignId('wallet_id')->constrained();
            $table->foreignId('vat_rate_id')->nullable()->constrained();
            $table->foreignId('category_id')->nullable()->constrained();
            $table->string('description')->default('');
            $table->decimal('net', 12, 2);
            $table->unsignedTinyInteger('day_of_month');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('transactions', function (Blueprint $table): void {
            $table->foreignId('recurring_income_id')->nullable()->after('recurrence_id')
                ->constrained('recurring_incomes')->nullOnDelete();
        });

        Schema::create('plan_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('plan_key')->unique();
            $table->date('date')->nullable();
            $table->boolean('cancelled')->default(false);
            $table->timestamps();
        });

        Schema::table('wallets', function (Blueprint $table): void {
            $table->date('concluded_until')->nullable()->after('statement_date');
        });

        Schema::create('bank_statements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->date('from_date');
            $table->date('to_date');
            $table->decimal('closing_balance', 12, 2)->nullable();
            $table->string('status', 10)->default('open');
            $table->timestamp('concluded_at')->nullable();
            $table->foreignId('concluded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['wallet_id', 'status']);
        });

        Schema::create('bank_statement_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bank_statement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label');
            $table->longText('decisions');
            $table->timestamps();
        });

        Schema::table('bank_lines', function (Blueprint $table): void {
            $table->foreignId('bank_statement_id')->nullable()->after('wallet_id')
                ->constrained()->nullOnDelete();
        });

        Schema::create('wallet_conclusions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_statement_id')->nullable()->constrained()->nullOnDelete();
            $table->date('until');
            $table->timestamps();
        });

        Schema::create('project_deliverables', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('net', 12, 2);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_deliverables');
        Schema::dropIfExists('wallet_conclusions');
        Schema::table('bank_lines', fn (Blueprint $table) => $table->dropConstrainedForeignId('bank_statement_id'));
        Schema::dropIfExists('bank_statement_steps');
        Schema::dropIfExists('bank_statements');
        Schema::table('wallets', fn (Blueprint $table) => $table->dropColumn('concluded_until'));
        Schema::dropIfExists('plan_adjustments');
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('recurring_income_id');
            $table->dropIndex(['is_planned']);
            $table->dropColumn(['is_planned', 'is_issued', 'invoice_pinned', 'is_edited', 'recurring_slot']);
        });
        Schema::dropIfExists('recurring_incomes');
    }
};
