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

test('setting the floor purges every managed row dated before it, reconciled included', function () {
    $wallet = Wallet::factory()->create();

    // A reconciled pre-floor tax row and an unreconciled pre-floor recurrence row —
    // both are leftovers the floor should sweep away.
    Transaction::factory()->create([
        'source' => 'tax',
        'managed_key' => 'tax:vat:2026-07',
        'date' => '2026-08-31',
        'is_reconciled' => true,
        'type' => 'expense',
        'net' => 100,
        'wallet_id' => $wallet->id,
    ]);
    Transaction::factory()->create([
        'source' => 'recurrence',
        'managed_key' => 'recurrence:1:2026-07-01',
        'date' => '2026-07-01',
        'is_reconciled' => false,
        'type' => 'expense',
        'net' => 50,
        'wallet_id' => $wallet->id,
    ]);
    // Manual history (source null) before the floor must NOT be touched.
    $manual = Transaction::factory()->create([
        'source' => null,
        'date' => '2026-07-15',
        'type' => 'expense',
        'net' => 10,
        'wallet_id' => $wallet->id,
    ]);

    $this->artisan('managed:from', ['date' => '2026-09-01'])->assertSuccessful();

    expect(Setting::current()->managed_from->toDateString())->toBe('2026-09-01');
    expect(Transaction::where('managed_key', 'tax:vat:2026-07')->exists())->toBeFalse();
    expect(Transaction::where('managed_key', 'recurrence:1:2026-07-01')->exists())->toBeFalse();
    expect(Transaction::find($manual->id))->not->toBeNull();
});
