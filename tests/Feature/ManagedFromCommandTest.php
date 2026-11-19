<?php

use App\Models\Setting;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-09-21');
});

afterEach(function () {
    Carbon::setTestNow();
});

test('the floor command sets the date the plan starts from and touches no transaction', function () {
    $wallet = Wallet::factory()->create();
    $kept = Transaction::factory()->create([
        'source' => 'recurrence',
        'managed_key' => 'recurrence:1:2026-07-01',
        'date' => '2026-07-01',
        'is_reconciled' => true,
        'type' => 'expense',
        'net' => 50,
        'wallet_id' => $wallet->id,
    ]);

    $this->artisan('managed:from', ['date' => '2026-09-01'])->assertSuccessful();

    expect(Setting::current()->managed_from->toDateString())->toBe('2026-09-01');
    expect(Transaction::find($kept->id))->not->toBeNull();
});
