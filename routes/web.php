<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\EntityController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\VatRateController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\WithheldTaxRateController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    // Finance area — gated by finance access.
    Route::middleware('can:access-finance')->group(function () {
        Route::get('transactions', [TransactionController::class, 'index'])->name('transactions.index');

        Route::get('wallets', [WalletController::class, 'index'])->name('wallets.index');
        Route::post('wallets', [WalletController::class, 'store'])->name('wallets.store');
        Route::patch('wallets/{wallet}', [WalletController::class, 'update'])->name('wallets.update');
        Route::delete('wallets/{wallet}', [WalletController::class, 'destroy'])->name('wallets.destroy');

        Route::get('entities', [EntityController::class, 'index'])->name('entities.index');
        Route::post('entities', [EntityController::class, 'store'])->name('entities.store');
        Route::patch('entities/{entity}', [EntityController::class, 'update'])->name('entities.update');
        Route::delete('entities/{entity}', [EntityController::class, 'destroy'])->name('entities.destroy');

        Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
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
    });

    // Business area — gated by CRM access.
    Route::middleware('can:access-crm')->group(function () {
        Route::inertia('leads', 'leads/index')->name('leads.index');
    });
});

require __DIR__.'/settings.php';
