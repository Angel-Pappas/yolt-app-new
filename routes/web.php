<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    // Finance area — gated by finance access.
    Route::inertia('transactions', 'transactions/index')
        ->middleware('can:access-finance')
        ->name('transactions.index');

    // Business area — gated by CRM access.
    Route::inertia('leads', 'leads/index')
        ->middleware('can:access-crm')
        ->name('leads.index');
});

require __DIR__.'/settings.php';
