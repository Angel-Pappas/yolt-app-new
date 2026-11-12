<?php

use App\Models\Agreement;
use App\Models\Entity;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-11-12');
});

test('home shows what needs attention', function () {
    $wallet = Wallet::factory()->create(['starting_balance' => 1000]);
    $income = fn (string $date, string $status, array $extra = []) => Transaction::factory()->create(array_merge([
        'type' => 'income', 'wallet_id' => $wallet->id, 'date' => $date, 'invoice_date' => $date,
        'net' => 100, 'vat_amount' => 24, 'income_status' => $status,
    ], $extra));
    $income('2026-11-05', 'invoiced');                        // overdue, this month
    $income('2026-10-20', 'agreed');                          // overdue, last month
    $income('2026-11-25', 'agreed');                          // due this month
    $income('2026-11-26', 'paid');                            // not open
    $income('2026-11-27', 'agreed', ['is_excluded' => true]); // switched off
    $income('2026-12-05', 'agreed');                          // next month
    Transaction::factory()->create(['type' => 'expense', 'wallet_id' => $wallet->id, 'source' => 'tax', 'managed_key' => 'tax:vat:2026-10', 'date' => '2026-11-30', 'net' => 400]);
    Agreement::forceCreate(['entity_id' => Entity::factory()->create()->id, 'title' => 'Lease', 'start_date' => '2026-01-01', 'end_date' => '2026-11-30']);
    Agreement::forceCreate(['entity_id' => Entity::factory()->create()->id, 'title' => 'Far', 'start_date' => '2026-01-01', 'end_date' => '2027-06-30']);

    $this->actingAs(User::factory()->create())->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('home')
            ->where('wallets.0.balance', 1000)
            ->where('income.overdue', ['count' => 2, 'total' => 248])
            ->where('income.this_month', ['count' => 2, 'total' => 248])
            ->where('taxes_this_month', 400)
            // The two overdue incomes (the excluded one and the tax row aside).
            ->where('to_reconcile', 2)
            ->has('agreements_ending', 1)
            ->where('agreements_ending.0.title', 'Lease')
            ->where('agreements_ending.0.days_left', 18)
            ->has('projection', 12)
            ->where('projection.0.month', '2026-11'));
});
