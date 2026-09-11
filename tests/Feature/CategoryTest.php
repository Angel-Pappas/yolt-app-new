<?php

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;

test('a finance user can view categories', function () {
    $user = User::factory()->withFinanceAccess()->create();
    Category::factory()->count(2)->create();

    $this->actingAs($user)->get('/categories')->assertOk();
});

test('a non-finance user cannot view categories', function () {
    $this->actingAs(User::factory()->create())
        ->get('/categories')
        ->assertForbidden();
});

test('a finance user can create a category', function () {
    $user = User::factory()->withFinanceAccess()->create();

    $this->actingAs($user)->post('/categories', [
        'name' => 'Fuel',
        'type' => 'expense',
    ])->assertRedirect();

    $category = Category::where('name', 'Fuel')->first();
    expect($category)->not->toBeNull();
    expect($category->type)->toBe('expense');
    expect($category->user_id)->toBe($user->id);
});

test('a category type must be income or expense', function () {
    $user = User::factory()->withFinanceAccess()->create();

    $this->actingAs($user)->post('/categories', [
        'name' => 'X',
        'type' => 'nonsense',
    ])->assertSessionHasErrors('type');
});

test('a finance user can update a category', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $category = Category::factory()->create(['name' => 'Old', 'type' => 'income']);

    $this->actingAs($user)->patch("/categories/{$category->id}", [
        'name' => 'New',
        'type' => 'expense',
    ])->assertRedirect();

    $category->refresh();
    expect($category->name)->toBe('New');
    expect($category->type)->toBe('expense');
});

test('an unused category is hard-deleted, not soft-deleted', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $category = Category::factory()->create();

    $this->actingAs($user)->delete("/categories/{$category->id}")->assertRedirect();

    // Removed for good — not kept as a soft-delete.
    expect(Category::withTrashed()->find($category->id))->toBeNull();
});

test('a category used by a live transaction cannot be deleted', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $category = Category::factory()->create(['type' => 'expense']);
    Transaction::factory()->create([
        'type' => 'expense',
        'category_id' => $category->id,
        'wallet_id' => Wallet::factory(),
    ]);

    $this->actingAs($user)->delete("/categories/{$category->id}")->assertRedirect();

    expect(Category::find($category->id))->not->toBeNull();
});

test('a category is deletable again once its transactions are soft-deleted', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $category = Category::factory()->create(['type' => 'expense']);
    $transaction = Transaction::factory()->create([
        'type' => 'expense',
        'category_id' => $category->id,
        'wallet_id' => Wallet::factory(),
    ]);
    $transaction->delete(); // soft delete — should no longer block

    $this->actingAs($user)->delete("/categories/{$category->id}")->assertRedirect();

    expect(Category::withTrashed()->find($category->id))->toBeNull();
});

test('a finance user can set a category description', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $category = Category::factory()->create(['type' => 'expense']);

    $this->actingAs($user)->patch("/categories/{$category->id}", [
        'name' => $category->name,
        'type' => 'expense',
        'description' => 'Company car costs',
    ])->assertRedirect();

    expect($category->refresh()->description)->toBe('Company car costs');
});

test('a finance user can open a category page', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $category = Category::factory()->create();

    $this->actingAs($user)->get("/categories/{$category->id}")->assertOk();
});

test('a non-finance user cannot create a category', function () {
    $this->actingAs(User::factory()->create())->post('/categories', [
        'name' => 'X',
        'type' => 'income',
    ])->assertForbidden();
});
