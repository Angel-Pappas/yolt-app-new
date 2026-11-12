<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DeMinimisAidController;
use App\Http\Controllers\EntityController;
use App\Http\Controllers\IncomeController;
use App\Http\Controllers\IncomeTaxYearController;
use App\Http\Controllers\OneOffController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectionController;
use App\Http\Controllers\PublicHolidayController;
use App\Http\Controllers\RateChangeController;
use App\Http\Controllers\RecurrenceController;
use App\Http\Controllers\TaxController;
use App\Http\Controllers\TaxRuleController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VatRateController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\WithheldTaxRateController;
use Illuminate\Support\Facades\Route;

// Every active user can use the whole app; a deactivated user is logged out by the
// EnsureAccountIsActive middleware. Admin-only areas add `can:admin`.
Route::middleware(['auth', 'verified'])->group(function () {
    // Home — the landing page after login (a welcome for now).
    Route::inertia('/', 'home')->name('home');

    // Finance — the day-to-day pages.
    Route::get('transactions', [TransactionController::class, 'index'])->name('transactions.index');
    Route::post('transactions', [TransactionController::class, 'store'])->name('transactions.store');
    // Bulk actions — registered before the {transaction} routes so "bulk" is
    // never parsed as a transaction id.
    Route::patch('transactions/bulk/category', [TransactionController::class, 'bulkCategory'])->name('transactions.bulk.category');
    Route::delete('transactions/bulk', [TransactionController::class, 'bulkDestroy'])->name('transactions.bulk.destroy');
    Route::patch('transactions/{transaction}', [TransactionController::class, 'update'])->name('transactions.update');
    Route::delete('transactions/{transaction}', [TransactionController::class, 'destroy'])->name('transactions.destroy');
    Route::patch('transactions/{transaction}/excluded', [TransactionController::class, 'exclude'])->name('transactions.exclude');
    Route::patch('transactions/{transaction}/status', [TransactionController::class, 'status'])->name('transactions.status');
    Route::post('transactions/{transaction}/reconcile', [TransactionController::class, 'reconcile'])->name('transactions.reconcile');
    Route::post('transactions/{transaction}/invoice', [TransactionController::class, 'invoice'])->name('transactions.invoice');
    Route::patch('transactions/{transaction}/description', [TransactionController::class, 'describe'])->name('transactions.describe');

    // One-off expenses: tagged transactions, listed apart (added/edited as transactions).
    Route::get('one-offs', [OneOffController::class, 'index'])->name('one-offs.index');

    // Income (every income with its status tag) and Projects (containers of income
    // installments).
    Route::get('projection', [ProjectionController::class, 'index'])->name('projection.index');
    Route::get('income', [IncomeController::class, 'index'])->name('income.index');
    Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::patch('projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::patch('projects/{project}/excluded', [ProjectController::class, 'exclude'])->name('projects.exclude');
    Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');

    // De minimis grants: the company's record, downloadable as Markdown or Excel.
    // Downloads are registered before the {deMinimisAid} routes.
    Route::get('de-minimis', [DeMinimisAidController::class, 'index'])->name('de-minimis.index');
    Route::get('de-minimis/download.md', [DeMinimisAidController::class, 'markdown'])->name('de-minimis.markdown');
    Route::get('de-minimis/download.xlsx', [DeMinimisAidController::class, 'excel'])->name('de-minimis.excel');
    Route::post('de-minimis', [DeMinimisAidController::class, 'store'])->name('de-minimis.store');
    Route::patch('de-minimis/{deMinimisAid}', [DeMinimisAidController::class, 'update'])->name('de-minimis.update');
    Route::delete('de-minimis/{deMinimisAid}', [DeMinimisAidController::class, 'destroy'])->name('de-minimis.destroy');

    Route::get('wallets', [WalletController::class, 'index'])->name('wallets.index');
    Route::post('wallets', [WalletController::class, 'store'])->name('wallets.store');
    Route::patch('wallets/{wallet}', [WalletController::class, 'update'])->name('wallets.update');
    Route::delete('wallets/{wallet}', [WalletController::class, 'destroy'])->name('wallets.destroy');

    Route::get('entities', [EntityController::class, 'index'])->name('entities.index');
    Route::post('entities', [EntityController::class, 'store'])->name('entities.store');
    // Per-type lists — static slugs, registered before the
    // numeric-constrained detail/update/delete routes so they don't collide.
    Route::get('entities/{slug}', [EntityController::class, 'byType'])
        ->whereIn('slug', ['customers', 'suppliers', 'contractors', 'employees', 'shareholders'])
        ->name('entities.type');
    Route::get('entities/{entity}', [EntityController::class, 'show'])->whereNumber('entity')->name('entities.show');
    Route::patch('entities/{entity}', [EntityController::class, 'update'])->whereNumber('entity')->name('entities.update');
    Route::patch('entities/{entity}/employee', [EntityController::class, 'updateEmployee'])->whereNumber('entity')->name('entities.employee');
    Route::get('payroll/preview', [PayrollController::class, 'preview'])->name('payroll.preview');
    Route::delete('entities/{entity}', [EntityController::class, 'destroy'])->whereNumber('entity')->name('entities.destroy');

    // Recurring transactions (defined on an entity's page; all listed on /recurring).
    Route::get('recurring', [RecurrenceController::class, 'index'])->name('recurring.index');
    Route::post('recurrences', [RecurrenceController::class, 'store'])->name('recurrences.store');
    Route::patch('recurrences/{recurrence}', [RecurrenceController::class, 'update'])->name('recurrences.update');
    Route::patch('recurrences/{recurrence}/excluded', [RecurrenceController::class, 'exclude'])->name('recurrences.exclude');
    Route::delete('recurrences/{recurrence}', [RecurrenceController::class, 'destroy'])->name('recurrences.destroy');

    Route::get('taxes', [TaxController::class, 'index'])->name('taxes.index');
    Route::patch('taxes/wallet', [TaxController::class, 'updateWallet'])->name('taxes.wallet');
    Route::get('taxes/vat', [TaxController::class, 'vat'])->name('taxes.vat');
    Route::get('taxes/withheld', [TaxController::class, 'withheld'])->name('taxes.withheld');
    Route::get('taxes/fmy', [TaxController::class, 'fmy'])->name('taxes.fmy');
    Route::get('taxes/efka', [TaxController::class, 'efka'])->name('taxes.efka');
    Route::get('taxes/income', [IncomeTaxYearController::class, 'index'])->name('income-tax.index');
    Route::post('taxes/income', [IncomeTaxYearController::class, 'store'])->name('income-tax.store');
    Route::patch('taxes/income/{incomeTaxYear}', [IncomeTaxYearController::class, 'update'])->name('income-tax.update');
    Route::delete('taxes/income/{incomeTaxYear}', [IncomeTaxYearController::class, 'destroy'])->name('income-tax.destroy');

    // Configuration — app setup lists, grouped under /configuration. The landing
    // page shows them as tiles; each opens its own list.
    Route::inertia('configuration', 'configuration/index')->name('configuration');

    Route::prefix('configuration')->group(function () {
        Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::get('categories/{category}', [CategoryController::class, 'show'])->name('categories.show');
        Route::patch('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

        // A rate's own page: its dated changes. `{kind}` is vat-rates or
        // withheld-tax-rates.
        Route::controller(RateChangeController::class)
            ->prefix('{kind}/{rate}')
            ->whereIn('kind', array_keys(RateChangeController::KINDS))
            ->whereNumber('rate')
            ->group(function () {
                Route::get('/', 'show')->name('rates.show');
                Route::post('changes', 'store')->name('rates.changes.store');
                Route::patch('changes/{change}', 'update')->name('rates.changes.update');
                Route::delete('changes/{change}', 'destroy')->name('rates.changes.destroy');
            });

        Route::get('tax-rules', [TaxRuleController::class, 'index'])->name('tax-rules.index');
        Route::post('tax-rules', [TaxRuleController::class, 'store'])->name('tax-rules.store');
        Route::patch('tax-rules/{taxRule}', [TaxRuleController::class, 'update'])->name('tax-rules.update');
        Route::delete('tax-rules/{taxRule}', [TaxRuleController::class, 'destroy'])->name('tax-rules.destroy');

        Route::get('vat-rates', [VatRateController::class, 'index'])->name('vat-rates.index');
        Route::post('vat-rates', [VatRateController::class, 'store'])->name('vat-rates.store');
        Route::patch('vat-rates/{vatRate}', [VatRateController::class, 'update'])->name('vat-rates.update');
        Route::delete('vat-rates/{vatRate}', [VatRateController::class, 'destroy'])->name('vat-rates.destroy');

        Route::get('withheld-tax-rates', [WithheldTaxRateController::class, 'index'])->name('withheld-tax-rates.index');
        Route::post('withheld-tax-rates', [WithheldTaxRateController::class, 'store'])->name('withheld-tax-rates.store');
        Route::patch('withheld-tax-rates/{withheldTaxRate}', [WithheldTaxRateController::class, 'update'])->name('withheld-tax-rates.update');
        Route::delete('withheld-tax-rates/{withheldTaxRate}', [WithheldTaxRateController::class, 'destroy'])->name('withheld-tax-rates.destroy');

        Route::get('public-holidays', [PublicHolidayController::class, 'index'])->name('public-holidays.index');
        Route::post('public-holidays', [PublicHolidayController::class, 'store'])->name('public-holidays.store');
        Route::patch('public-holidays/{publicHoliday}', [PublicHolidayController::class, 'update'])->name('public-holidays.update');
        Route::delete('public-holidays/{publicHoliday}', [PublicHolidayController::class, 'destroy'])->name('public-holidays.destroy');

        // Users — admin only.
        Route::middleware('can:admin')->group(function () {
            Route::get('users', [UserController::class, 'index'])->name('users.index');
            Route::post('users', [UserController::class, 'store'])->name('users.store');
            Route::patch('users/{user}', [UserController::class, 'update'])->name('users.update');
        });
    });
});

require __DIR__.'/settings.php';
