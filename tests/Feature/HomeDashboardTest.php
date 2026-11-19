<?php

use App\Models\Agreement;
use App\Models\Entity;
use App\Models\PlannedTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-11-12');
});

test('home shows what needs attention', function () {
    $wallet = Wallet::factory()->create(['starting_balance' => 1000]);
    $customer = Entity::factory()->customer()->create();
    $line = fn (string $date, array $extra = []) => PlannedTransaction::query()->create(array_merge([
        'type' => 'income', 'wallet_id' => $wallet->id, 'entity_id' => $customer->id, 'date' => $date,
        'invoice_date' => $date, 'description' => '', 'net' => 100, 'vat_amount' => 24, 'withheld_amount' => 0,
    ], $extra));
    $line('2026-10-20');                          // owed since last month
    $line('2026-11-05');                          // this month
    $line('2026-11-25');                          // this month
    $paid = $line('2026-11-26');                  // paid below
    $line('2026-11-27', ['is_excluded' => true]); // switched off
    $line('2026-12-05');                          // next month
    Transaction::factory()->create([
        'type' => 'income', 'wallet_id' => $wallet->id, 'entity_id' => $customer->id, 'date' => '2026-11-10',
        'invoice_date' => '2026-11-26', 'net' => 100, 'vat_amount' => 24, 'is_reconciled' => true,
        'managed_key' => $paid->planKey(),
    ]);
    Agreement::forceCreate(['entity_id' => Entity::factory()->create()->id, 'title' => 'Lease', 'start_date' => '2026-01-01', 'end_date' => '2026-11-30']);
    Agreement::forceCreate(['entity_id' => Entity::factory()->create()->id, 'title' => 'Far', 'start_date' => '2026-01-01', 'end_date' => '2027-06-30']);

    $this->actingAs(User::factory()->create())->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('home')
            // The wallet has the paid 124 on top of its start (reconciled, dated ≤ today).
            ->where('wallets.0.balance', 1124)
            ->where('income.overdue', ['count' => 1, 'total' => 124])
            ->where('income.this_month', ['count' => 2, 'total' => 248])
            // October's VAT (the 20/10 line), due 30/11.
            ->where('taxes_this_month', 24)
            ->where('not_matched', 0)
            ->has('agreements_ending', 1)
            ->where('agreements_ending.0.title', 'Lease')
            ->where('agreements_ending.0.days_left', 18)
            ->has('projection', 12)
            ->where('projection.0.month', '2026-11'));
});
