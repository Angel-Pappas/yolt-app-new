<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\EntityController;
use App\Http\Controllers\PublicHolidayController;
use App\Http\Controllers\TaxController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VatRateController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\WithheldTaxRateController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

// Every active user can use the whole app; a deactivated user is logged out by the
// EnsureAccountIsActive middleware. Admin-only areas add `can:admin`.
Route::middleware(['auth', 'verified'])->group(function () {
    // Finance — the day-to-day pages.
    Route::get('transactions', [TransactionController::class, 'index'])->name('transactions.index');
    Route::post('transactions', [TransactionController::class, 'store'])->name('transactions.store');
    // Bulk actions — registered before the {transaction} routes so "bulk" is
    // never parsed as a transaction id.
    Route::patch('transactions/bulk/category', [TransactionController::class, 'bulkCategory'])->name('transactions.bulk.category');
    Route::delete('transactions/bulk', [TransactionController::class, 'bulkDestroy'])->name('transactions.bulk.destroy');
    Route::patch('transactions/{transaction}', [TransactionController::class, 'update'])->name('transactions.update');
    Route::delete('transactions/{transaction}', [TransactionController::class, 'destroy'])->name('transactions.destroy');
    Route::post('transactions/{transaction}/reconcile', [TransactionController::class, 'reconcile'])->name('transactions.reconcile');
    Route::post('transactions/{transaction}/invoice', [TransactionController::class, 'invoice'])->name('transactions.invoice');

    Route::get('wallets', [WalletController::class, 'index'])->name('wallets.index');
    Route::post('wallets', [WalletController::class, 'store'])->name('wallets.store');
    Route::patch('wallets/{wallet}', [WalletController::class, 'update'])->name('wallets.update');
    Route::delete('wallets/{wallet}', [WalletController::class, 'destroy'])->name('wallets.destroy');

    Route::get('entities', [EntityController::class, 'index'])->name('entities.index');
    Route::post('entities', [EntityController::class, 'store'])->name('entities.store');
    Route::patch('entities/{entity}', [EntityController::class, 'update'])->name('entities.update');
    Route::delete('entities/{entity}', [EntityController::class, 'destroy'])->name('entities.destroy');

    Route::get('taxes', [TaxController::class, 'index'])->name('taxes.index');
    Route::get('taxes/vat', [TaxController::class, 'vat'])->name('taxes.vat');
    Route::get('taxes/withheld', [TaxController::class, 'withheld'])->name('taxes.withheld');

    // Configuration — app setup lists, grouped under /configuration. The landing
    // page shows them as tiles; each opens its own list.
    Route::inertia('configuration', 'configuration/index')->name('configuration');

    Route::prefix('configuration')->group(function () {
        Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::get('categories/{category}', [CategoryController::class, 'show'])->name('categories.show');
        Route::patch('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

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
