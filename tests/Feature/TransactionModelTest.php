<?php

use App\Models\Category;
use App\Models\Entity;
use App\Models\Transaction;
use App\Models\TransactionVatLine;
use App\Models\Wallet;

test('a transaction relates to its wallet, entity, category and lines', function () {
    $wallet = Wallet::factory()->create();
    $entity = Entity::factory()->create();
    $category = Category::factory()->create(['type' => 'expense']);

    $transaction = Transaction::factory()->create([
        'wallet_id' => $wallet->id,
        'entity_id' => $entity->id,
        'category_id' => $category->id,
        'type' => 'expense',
        'net' => 100,
    ]);

    $transaction->vatLines()->create(['net' => 100, 'vat_amount' => 24]);
    $transaction->withheldLines()->create(['net' => 100, 'withheld_amount' => 20]);

    expect($transaction->wallet->id)->toBe($wallet->id);
    expect($transaction->entity->id)->toBe($entity->id);
    expect($transaction->category->id)->toBe($category->id);
    expect($transaction->vatLines)->toHaveCount(1);
    expect($transaction->withheldLines)->toHaveCount(1);
});

test('a transaction soft-deletes', function () {
    $transaction = Transaction::factory()->create();

    $transaction->delete();

    expect(Transaction::find($transaction->id))->toBeNull();
    expect(Transaction::withTrashed()->find($transaction->id))->not->toBeNull();
});

test('a transfer references a second wallet', function () {
    $from = Wallet::factory()->create();
    $to = Wallet::factory()->create();

    $transfer = Transaction::factory()->create([
        'type' => 'transfer',
        'wallet_id' => $from->id,
        'to_wallet_id' => $to->id,
    ]);

    expect($transfer->toWallet->id)->toBe($to->id);
});

test('hard-deleting a transaction cascades to its VAT lines', function () {
    $transaction = Transaction::factory()->create();
    $transaction->vatLines()->create(['net' => 10, 'vat_amount' => 2]);

    $transaction->forceDelete();

    expect(TransactionVatLine::count())->toBe(0);
});
