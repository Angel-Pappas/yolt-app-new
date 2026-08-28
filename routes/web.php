<?php

use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    // Finance area — gated by finance access.
    Route::middleware('can:access-finance')->group(function () {
        Route::inertia('transactions', 'transactions/index')->name('transactions.index');

        Route::get('wallets', [WalletController::class, 'index'])->name('wallets.index');
        Route::post('wallets', [WalletController::class, 'store'])->name('wallets.store');
        Route::patch('wallets/{wallet}', [WalletController::class, 'update'])->name('wallets.update');
        Route::delete('wallets/{wallet}', [WalletController::class, 'destroy'])->name('wallets.destroy');
    });

    // Business area — gated by CRM access.
    Route::middleware('can:access-crm')->group(function () {
        Route::inertia('leads', 'leads/index')->name('leads.index');
    });
});

require __DIR__.'/settings.php';
