<?php

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;

test('a finance user can move selected transactions to another category', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $wallet = Wallet::factory()->create();
    $fuel = Category::factory()->create(['type' => 'expense']);
    $car = Category::factory()->create(['type' => 'expense']);

    $ids = Transaction::factory()->count(3)->create([
        'type' => 'expense',
        'category_id' => $fuel->id,
        'wallet_id' => $wallet->id,
    ])->pluck('id')->all();

    $this->actingAs($user)->patch('/transactions/bulk/category', [
        'ids' => $ids,
        'category_id' => $car->id,
    ])->assertRedirect();

    expect(Transaction::where('category_id', $car->id)->count())->toBe(3);
    expect(Transaction::where('category_id', $fuel->id)->count())->toBe(0);
});

test('a bulk move does not touch transactions of a different type than the target category', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $wallet = Wallet::factory()->create();
    $incomeCategory = Category::factory()->create(['type' => 'income']);

    $expense = Transaction::factory()->create([
        'type' => 'expense',
        'category_id' => null,
        'wallet_id' => $wallet->id,
    ]);

    $this->actingAs($user)->patch('/transactions/bulk/category', [
        'ids' => [$expense->id],
        'category_id' => $incomeCategory->id,
    ])->assertRedirect();

    // Type mismatch — the expense keeps its (null) category.
    expect($expense->refresh()->category_id)->toBeNull();
});

test('a finance user can bulk-delete selected transactions', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $wallet = Wallet::factory()->create();

    $ids = Transaction::factory()->count(2)->create([
        'type' => 'expense',
        'wallet_id' => $wallet->id,
    ])->pluck('id')->all();

    $this->actingAs($user)->delete('/transactions/bulk', ['ids' => $ids])
        ->assertRedirect();

    expect(Transaction::whereIn('id', $ids)->count())->toBe(0);
    expect(Transaction::withTrashed()->whereIn('id', $ids)->count())->toBe(2);
});

test('a non-finance user cannot run bulk actions', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->create();
    $transaction = Transaction::factory()->create(['wallet_id' => $wallet->id]);

    $this->actingAs($user)->delete('/transactions/bulk', ['ids' => [$transaction->id]])
        ->assertForbidden();
});
