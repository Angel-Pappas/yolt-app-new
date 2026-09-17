<?php

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;

test('a finance user can view categories', function () {
    $user = User::factory()->create();
    Category::factory()->count(2)->create();

    $this->actingAs($user)->get('/configuration/categories')->assertOk();
});

test('a guest cannot view categories', function () {
    $this->get('/configuration/categories')->assertRedirect(route('login'));
});

test('a finance user can create a category', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/configuration/categories', [
        'name' => 'Fuel',
        'type' => 'expense',
    ])->assertRedirect();

    $category = Category::where('name', 'Fuel')->first();
    expect($category)->not->toBeNull();
    expect($category->type)->toBe('expense');
    expect($category->user_id)->toBe($user->id);
});

test('creating a category as "both" makes one income and one expense', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/configuration/categories', [
        'name' => 'Travel',
        'type' => 'both',
        'description' => 'Trips',
    ])->assertRedirect();

    $rows = Category::where('name', 'Travel')->get();
    expect($rows)->toHaveCount(2);
    expect($rows->pluck('type')->sort()->values()->all())->toBe([
        'expense',
        'income',
    ]);
    // Both carry the same description but are otherwise independent rows.
    expect($rows->pluck('description')->unique()->all())->toBe(['Trips']);
});

test('a category type must be income or expense', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/configuration/categories', [
        'name' => 'X',
        'type' => 'nonsense',
    ])->assertSessionHasErrors('type');
});

test('a finance user can update a category', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create(['name' => 'Old', 'type' => 'income']);

    $this->actingAs($user)->patch("/configuration/categories/{$category->id}", [
        'name' => 'New',
        'type' => 'expense',
    ])->assertRedirect();

    $category->refresh();
    expect($category->name)->toBe('New');
    expect($category->type)->toBe('expense');
});

test('an unused category is hard-deleted, not soft-deleted', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create();

    $this->actingAs($user)->delete("/configuration/categories/{$category->id}")->assertRedirect();

    // Removed for good — not kept as a soft-delete.
    expect(Category::withTrashed()->find($category->id))->toBeNull();
});

test('a category used by a live transaction cannot be deleted', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create(['type' => 'expense']);
    Transaction::factory()->create([
        'type' => 'expense',
        'category_id' => $category->id,
        'wallet_id' => Wallet::factory(),
    ]);

    $this->actingAs($user)->delete("/configuration/categories/{$category->id}")->assertRedirect();

    expect(Category::find($category->id))->not->toBeNull();
});

test('a category is deletable again once its transactions are soft-deleted', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create(['type' => 'expense']);
    $transaction = Transaction::factory()->create([
        'type' => 'expense',
        'category_id' => $category->id,
        'wallet_id' => Wallet::factory(),
    ]);
    $transaction->delete(); // soft delete — should no longer block

    $this->actingAs($user)->delete("/configuration/categories/{$category->id}")->assertRedirect();

    expect(Category::withTrashed()->find($category->id))->toBeNull();
});

test('a finance user can set a category description', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create(['type' => 'expense']);

    $this->actingAs($user)->patch("/configuration/categories/{$category->id}", [
        'name' => $category->name,
        'type' => 'expense',
        'description' => 'Company car costs',
    ])->assertRedirect();

    expect($category->refresh()->description)->toBe('Company car costs');
});

test('a finance user can open a category page', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create();

    $this->actingAs($user)->get("/configuration/categories/{$category->id}")->assertOk();
});

test('the category page carries transaction lines and lookups for the edit dialog', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create(['type' => 'expense']);
    $transaction = Transaction::factory()->create([
        'type' => 'expense',
        'category_id' => $category->id,
        'wallet_id' => Wallet::factory(),
    ]);
    $transaction->vatLines()->create([
        'net' => '100.00', 'vat_rate_id' => null, 'vat_amount' => '0', 'position' => 0,
    ]);

    $this->actingAs($user)->get("/configuration/categories/{$category->id}")
        ->assertInertia(fn ($page) => $page
            ->component('categories/show')
            ->has('transactions', 1)
            ->has('transactions.0.vat_lines', 1)
            ->has('transactions.0.withheld_lines')
            // The edit form's lookups come from the globally-shared financeLookups.
            ->has('financeLookups.wallets')
            ->has('financeLookups.vatRates')
            ->has('financeLookups.withheldRates')
        );
});

test('a guest cannot create a category', function () {
    $this->post('/configuration/categories', [
        'name' => 'X',
        'type' => 'income',
    ])->assertRedirect(route('login'));
});
