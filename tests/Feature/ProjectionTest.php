<?php

use App\Models\Category;
use App\Models\Entity;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Support\AmountLines;
use App\Support\Projection;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-11-12');
});

/** A counted, reconciled row unless told otherwise. */
function projectionRow(Wallet $wallet, string $type, string $date, float $net, array $extra = []): Transaction
{
    return Transaction::factory()->create(array_merge([
        'type' => $type, 'wallet_id' => $wallet->id, 'date' => $date, 'invoice_date' => $date,
        'net' => $net, 'vat_amount' => 0, 'withheld_amount' => 0, 'is_reconciled' => true,
        'entity_id' => null, 'category_id' => null,
    ], $extra));
}

test('each month starts where the last one ended', function () {
    $wallet = Wallet::factory()->create(['starting_balance' => 1000]);
    projectionRow($wallet, 'income', '2026-09-10', 500);          // before the range
    projectionRow($wallet, 'income', '2026-10-05', 2000, ['vat_amount' => 480]);
    projectionRow($wallet, 'expense', '2026-10-20', 300);
    projectionRow($wallet, 'expense', '2026-11-20', 700, ['is_reconciled' => false]);

    $rows = Projection::monthly('2026-10', '2026-12');

    expect($rows)->toHaveCount(3);
    expect($rows[0])->toMatchArray(['month' => '2026-10', 'starting' => 1500.0, 'income' => 2480.0, 'expenses' => 300.0, 'net' => 2180.0, 'ending' => 3680.0]);
    expect($rows[1])->toMatchArray(['starting' => 3680.0, 'expenses' => 700.0, 'ending' => 2980.0]);
    expect($rows[2])->toMatchArray(['starting' => 2980.0, 'net' => 0.0, 'ending' => 2980.0]);
});

test('money out is split into payroll, contractors, taxes and other', function () {
    $wallet = Wallet::factory()->create(['starting_balance' => 0]);
    $payroll = Category::factory()->create(['type' => 'expense', 'name' => AmountLines::PAYROLL_CATEGORY]);
    projectionRow($wallet, 'expense', '2026-11-02', 1000, ['entity_id' => Entity::factory()->create(['type' => 'employee'])->id, 'category_id' => $payroll->id, 'fmy_amount' => 50, 'efka_employee_amount' => 130, 'efka_employer_amount' => 220]);
    projectionRow($wallet, 'expense', '2026-11-03', 1000, ['entity_id' => Entity::factory()->create(['type' => 'contractor'])->id, 'vat_amount' => 240, 'withheld_amount' => 200]);
    projectionRow($wallet, 'expense', '2026-11-04', 400, ['source' => 'tax', 'managed_key' => 'tax:vat:2026-10']);
    projectionRow($wallet, 'expense', '2026-11-05', 99, ['entity_id' => Entity::factory()->create(['type' => 'supplier'])->id]);

    $row = Projection::monthly('2026-11', '2026-11')[0];

    // Payroll moves "to pay": 1.000 − 50 − 130; the contractor net + VAT − withheld.
    expect($row)->toMatchArray(['payroll' => 820.0, 'contractors' => 1040.0, 'taxes' => 400.0, 'other' => 99.0, 'expenses' => 2359.0]);
});

test('excluded and dropped rows are left out', function () {
    $wallet = Wallet::factory()->create(['starting_balance' => 0]);
    projectionRow($wallet, 'income', '2026-11-02', 100);
    projectionRow($wallet, 'income', '2026-11-03', 1000, ['is_excluded' => true, 'is_reconciled' => false]);
    projectionRow($wallet, 'income', '2026-11-04', 1000, ['income_status' => 'dropped', 'is_reconciled' => false]);

    expect(Projection::monthly('2026-11', '2026-11')[0]['income'])->toBe(100.0);
});

test('unreconciled past rows can be left out; future ones always count', function () {
    $wallet = Wallet::factory()->create(['starting_balance' => 0]);
    projectionRow($wallet, 'income', '2026-11-02', 100, ['is_reconciled' => false]); // past, pending
    projectionRow($wallet, 'income', '2026-11-20', 200, ['is_reconciled' => false]); // future

    expect(Projection::monthly('2026-11', '2026-11', null, true)[0]['income'])->toBe(300.0);
    expect(Projection::monthly('2026-11', '2026-11', null, false)[0]['income'])->toBe(200.0);
});

test('a wallet selection counts its own rows and shows transfers across its edge', function () {
    $bank = Wallet::factory()->create(['starting_balance' => 1000]);
    $cash = Wallet::factory()->create(['starting_balance' => 50]);
    projectionRow($bank, 'income', '2026-11-02', 500);
    projectionRow($cash, 'expense', '2026-11-03', 20);
    projectionRow($bank, 'transfer', '2026-11-04', 300, ['to_wallet_id' => $cash->id]);

    $all = Projection::monthly('2026-11', '2026-11')[0];
    expect($all)->toMatchArray(['starting' => 1050.0, 'transfers' => 0.0, 'ending' => 1530.0]);

    $bankOnly = Projection::monthly('2026-11', '2026-11', [$bank->id])[0];
    expect($bankOnly)->toMatchArray(['starting' => 1000.0, 'income' => 500.0, 'expenses' => 0.0, 'transfers' => -300.0, 'ending' => 1200.0]);

    $cashOnly = Projection::monthly('2026-11', '2026-11', [$cash->id])[0];
    expect($cashOnly)->toMatchArray(['starting' => 50.0, 'transfers' => 300.0, 'ending' => 330.0]);
});

test('the view someone sets up is remembered for their next visit', function () {
    $wallet = Wallet::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)->get("/projection?back=1&ahead=2&wallets[]={$wallet->id}&pending=0")->assertOk();

    expect($user->refresh()->preferences['projection'])->toBe(['back' => 1, 'ahead' => 2, 'wallets' => [$wallet->id], 'pending' => false]);

    $this->actingAs($user)->get('/projection')
        ->assertInertia(fn ($page) => $page
            ->component('projection/index')
            ->has('rows', 4)
            ->where('settings.pending', false)
            ->where('settings.wallets', [$wallet->id]));
});

test('the projection opens on its defaults: three months back, a year ahead', function () {
    $this->actingAs(User::factory()->create())->get('/projection')
        ->assertInertia(fn ($page) => $page->has('rows', 16)->where('rows.0.month', '2026-08')->where('current_month', '2026-11'));
});
