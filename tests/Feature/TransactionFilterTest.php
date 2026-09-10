<?php

use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Inertia\Testing\AssertableInertia as Assert;

// The date range and the invoice-date range (Taxes drill-down) are the only
// server-side filters on the transactions list. Search and per-column filtering
// (type, wallet, category, entity, amounts) happen client-side in the shared list
// view, so they're not asserted against the server payload here.

test('the invoice-date filter narrows by invoice date (Taxes drill-down)', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $wallet = Wallet::factory()->create();
    Transaction::factory()->create(['wallet_id' => $wallet->id, 'invoice_date' => '2026-07-15']);
    Transaction::factory()->create(['wallet_id' => $wallet->id, 'invoice_date' => '2026-08-15']);

    $this->actingAs($user)
        ->get('/transactions?invoice_from=2026-07-01&invoice_to=2026-07-31')
        ->assertInertia(fn (Assert $page) => $page->has('transactions', 1));
});

test('filtering by a date range', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $wallet = Wallet::factory()->create();
    Transaction::factory()->create(['wallet_id' => $wallet->id, 'date' => '2026-01-15']);
    Transaction::factory()->create(['wallet_id' => $wallet->id, 'date' => '2026-08-15']);

    $this->actingAs($user)
        ->get('/transactions?from=2026-08-01&to=2026-08-31')
        ->assertInertia(fn (Assert $page) => $page->has('transactions', 1));
});
