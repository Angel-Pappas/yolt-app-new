<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\EntityController;
use App\Http\Controllers\LeadActionController;
use App\Http\Controllers\LeadContactController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\LeadOriginController;
use App\Http\Controllers\LeadStatusController;
use App\Http\Controllers\ProjectActionController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectStatusController;
use App\Http\Controllers\TaxController;
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
        Route::post('transactions', [TransactionController::class, 'store'])->name('transactions.store');
        Route::patch('transactions/{transaction}', [TransactionController::class, 'update'])->name('transactions.update');
        Route::delete('transactions/{transaction}', [TransactionController::class, 'destroy'])->name('transactions.destroy');

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

        Route::get('taxes', [TaxController::class, 'index'])->name('taxes.index');
        Route::get('taxes/vat', [TaxController::class, 'vat'])->name('taxes.vat');
        Route::get('taxes/withheld', [TaxController::class, 'withheld'])->name('taxes.withheld');

        Route::get('withheld-tax-rates', [WithheldTaxRateController::class, 'index'])->name('withheld-tax-rates.index');
        Route::post('withheld-tax-rates', [WithheldTaxRateController::class, 'store'])->name('withheld-tax-rates.store');
        Route::patch('withheld-tax-rates/{withheldTaxRate}', [WithheldTaxRateController::class, 'update'])->name('withheld-tax-rates.update');
        Route::delete('withheld-tax-rates/{withheldTaxRate}', [WithheldTaxRateController::class, 'destroy'])->name('withheld-tax-rates.destroy');
    });

    // Business area — gated by CRM access.
    Route::middleware('can:access-crm')->group(function () {
        Route::get('leads', [LeadController::class, 'index'])->name('leads.index');
        Route::post('leads', [LeadController::class, 'store'])->name('leads.store');
        Route::get('leads/{lead}', [LeadController::class, 'show'])->name('leads.show');
        Route::patch('leads/{lead}', [LeadController::class, 'update'])->name('leads.update');
        Route::delete('leads/{lead}', [LeadController::class, 'destroy'])->name('leads.destroy');

        Route::post('leads/{lead}/actions', [LeadActionController::class, 'store'])->name('leads.actions.store');
        Route::patch('leads/{lead}/actions/{action}', [LeadActionController::class, 'update'])->name('leads.actions.update');
        Route::delete('leads/{lead}/actions/{action}', [LeadActionController::class, 'destroy'])->name('leads.actions.destroy');

        Route::post('leads/{lead}/contacts', [LeadContactController::class, 'store'])->name('leads.contacts.store');
        Route::patch('leads/{lead}/contacts/{contact}', [LeadContactController::class, 'update'])->name('leads.contacts.update');
        Route::delete('leads/{lead}/contacts/{contact}', [LeadContactController::class, 'destroy'])->name('leads.contacts.destroy');

        Route::get('lead-statuses', [LeadStatusController::class, 'index'])->name('lead-statuses.index');
        Route::post('lead-statuses', [LeadStatusController::class, 'store'])->name('lead-statuses.store');
        Route::patch('lead-statuses/{leadStatus}', [LeadStatusController::class, 'update'])->name('lead-statuses.update');
        Route::delete('lead-statuses/{leadStatus}', [LeadStatusController::class, 'destroy'])->name('lead-statuses.destroy');

        Route::get('lead-origins', [LeadOriginController::class, 'index'])->name('lead-origins.index');
        Route::post('lead-origins', [LeadOriginController::class, 'store'])->name('lead-origins.store');
        Route::patch('lead-origins/{leadOrigin}', [LeadOriginController::class, 'update'])->name('lead-origins.update');
        Route::delete('lead-origins/{leadOrigin}', [LeadOriginController::class, 'destroy'])->name('lead-origins.destroy');

        Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
        Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');
        Route::get('projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
        Route::patch('projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
        Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');

        Route::post('projects/{project}/actions', [ProjectActionController::class, 'store'])->name('projects.actions.store');
        Route::patch('projects/{project}/actions/{action}', [ProjectActionController::class, 'update'])->name('projects.actions.update');
        Route::delete('projects/{project}/actions/{action}', [ProjectActionController::class, 'destroy'])->name('projects.actions.destroy');

        Route::get('project-statuses', [ProjectStatusController::class, 'index'])->name('project-statuses.index');
        Route::post('project-statuses', [ProjectStatusController::class, 'store'])->name('project-statuses.store');
        Route::patch('project-statuses/{projectStatus}', [ProjectStatusController::class, 'update'])->name('project-statuses.update');
        Route::delete('project-statuses/{projectStatus}', [ProjectStatusController::class, 'destroy'])->name('project-statuses.destroy');
    });
});

require __DIR__.'/settings.php';
