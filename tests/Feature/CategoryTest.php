<?php

use App\Models\Category;
use App\Models\User;

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

test('a finance user can soft-delete a category', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $category = Category::factory()->create();

    $this->actingAs($user)->delete("/categories/{$category->id}")->assertRedirect();

    expect(Category::find($category->id))->toBeNull();
    expect(Category::withTrashed()->find($category->id))->not->toBeNull();
});

test('a non-finance user cannot create a category', function () {
    $this->actingAs(User::factory()->create())->post('/categories', [
        'name' => 'X',
        'type' => 'income',
    ])->assertForbidden();
});
