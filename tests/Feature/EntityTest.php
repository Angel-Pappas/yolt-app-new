<?php

use App\Models\Entity;
use App\Models\User;

test('a finance user can view the entities page', function () {
    $user = User::factory()->withFinanceAccess()->create();
    Entity::factory()->count(2)->create();

    $this->actingAs($user)->get('/entities')->assertOk();
});

test('a non-finance user cannot view entities', function () {
    $this->actingAs(User::factory()->create())
        ->get('/entities')
        ->assertForbidden();
});

test('a finance user can create an entity', function () {
    $user = User::factory()->withFinanceAccess()->create();

    $this->actingAs($user)->post('/entities', [
        'name' => 'ACME',
        'vat_number' => 'EL123456789',
    ])->assertRedirect();

    $entity = Entity::where('name', 'ACME')->first();
    expect($entity)->not->toBeNull();
    expect($entity->vat_number)->toBe('EL123456789');
    expect($entity->user_id)->toBe($user->id);
});

test('an empty vat number is stored as null', function () {
    $user = User::factory()->withFinanceAccess()->create();

    $this->actingAs($user)->post('/entities', [
        'name' => 'No VAT',
        'vat_number' => '',
    ])->assertRedirect();

    expect(Entity::where('name', 'No VAT')->first()->vat_number)->toBeNull();
});

test('creating an entity requires a name', function () {
    $user = User::factory()->withFinanceAccess()->create();

    $this->actingAs($user)->post('/entities', [
        'name' => '',
        'vat_number' => '',
    ])->assertSessionHasErrors('name');
});

test('a finance user can update an entity', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $entity = Entity::factory()->create(['name' => 'Old']);

    $this->actingAs($user)->patch("/entities/{$entity->id}", [
        'name' => 'New',
        'vat_number' => '',
    ])->assertRedirect();

    expect($entity->refresh()->name)->toBe('New');
});

test('a finance user can soft-delete an entity', function () {
    $user = User::factory()->withFinanceAccess()->create();
    $entity = Entity::factory()->create();

    $this->actingAs($user)->delete("/entities/{$entity->id}")->assertRedirect();

    expect(Entity::find($entity->id))->toBeNull();
    expect(Entity::withTrashed()->find($entity->id))->not->toBeNull();
});

test('a non-finance user cannot create an entity', function () {
    $this->actingAs(User::factory()->create())
        ->post('/entities', ['name' => 'X'])
        ->assertForbidden();
});
