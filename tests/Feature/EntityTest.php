<?php

use App\Models\Entity;
use App\Models\User;

test('a finance user can view a per-type entities list', function () {
    $user = User::factory()->create();
    Entity::factory()->customer()->count(2)->create();

    $this->actingAs($user)->get('/entities/customers')->assertOk();
});

test('the Cheese bucket shows only unclassified entities', function () {
    $user = User::factory()->create();
    $cheese = Entity::factory()->create(['name' => 'Unsorted Co']);
    Entity::factory()->supplier()->create(['name' => 'A Supplier']);

    $this->actingAs($user)
        ->get('/entities/cheese')
        ->assertInertia(
            fn ($page) => $page
                ->component('entities/list')
                ->where('classify', true)
                ->has('entities', 1)
                ->where('entities.0.id', $cheese->id),
        );
});

test('a suppliers list shows only suppliers', function () {
    $user = User::factory()->create();
    Entity::factory()->supplier()->create();
    Entity::factory()->customer()->create();

    $this->actingAs($user)
        ->get('/entities/suppliers')
        ->assertInertia(fn ($page) => $page->has('entities', 1));
});

test('a bare /entities redirects to a list', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/entities')->assertRedirect('/entities/customers');
});

test('a guest cannot view entities', function () {
    $this->get('/entities/customers')->assertRedirect(route('login'));
});

test('a finance user can create an entity', function () {
    $user = User::factory()->create();

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
    $user = User::factory()->create();

    $this->actingAs($user)->post('/entities', [
        'name' => 'No VAT',
        'vat_number' => '',
    ])->assertRedirect();

    expect(Entity::where('name', 'No VAT')->first()->vat_number)->toBeNull();
});

test('an entity can be created with no vat_number field at all (inline create)', function () {
    $user = User::factory()->create();

    // The transaction form's inline "Create" sends only a name — the vat_number
    // key is absent, which used to throw "Undefined array key".
    $this->actingAs($user)->post('/entities', [
        'name' => 'Inline Co',
    ])->assertRedirect();

    $entity = Entity::where('name', 'Inline Co')->first();
    expect($entity)->not->toBeNull();
    expect($entity->vat_number)->toBeNull();
});

test('creating an entity requires a name', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/entities', [
        'name' => '',
        'vat_number' => '',
    ])->assertSessionHasErrors('name');
});

test('a finance user can update an entity', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->create(['name' => 'Old']);

    $this->actingAs($user)->patch("/entities/{$entity->id}", [
        'name' => 'New',
        'vat_number' => '',
    ])->assertRedirect();

    expect($entity->refresh()->name)->toBe('New');
});

test('a finance user can soft-delete an entity', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->create();

    $this->actingAs($user)->delete("/entities/{$entity->id}")->assertRedirect();

    expect(Entity::find($entity->id))->toBeNull();
    expect(Entity::withTrashed()->find($entity->id))->not->toBeNull();
});

test('a guest cannot create an entity', function () {
    $this->post('/entities', ['name' => 'X'])->assertRedirect(route('login'));
});

test('an entity can be created with a type', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/entities', [
        'name' => 'A Customer',
        'type' => 'customer',
    ])->assertRedirect();

    expect(Entity::where('name', 'A Customer')->first()->type)->toBe('customer');
});

test('an invalid entity type is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/entities', [
        'name' => 'Bad',
        'type' => 'alien',
    ])->assertSessionHasErrors('type');
});

test('entities can be bulk-classified into a type', function () {
    $user = User::factory()->create();
    $a = Entity::factory()->create();
    $b = Entity::factory()->create();

    $this->actingAs($user)->patch('/entities/bulk/type', [
        'ids' => [$a->id, $b->id],
        'type' => 'supplier',
    ])->assertRedirect();

    expect($a->refresh()->type)->toBe('supplier');
    expect($b->refresh()->type)->toBe('supplier');
});

test('the shared entity lookup carries the entity type', function () {
    $user = User::factory()->create();
    Entity::factory()->customer()->create();

    $this->actingAs($user)
        ->get('/entities/customers')
        ->assertInertia(
            fn ($page) => $page->has('financeLookups.entities.0.type'),
        );
});

test('an entity has its own detail page', function () {
    $user = User::factory()->create();
    $entity = Entity::factory()->supplier()->create(['name' => 'Detail Co']);

    $this->actingAs($user)
        ->get("/entities/{$entity->id}")
        ->assertInertia(
            fn ($page) => $page
                ->component('entities/show')
                ->where('entity.name', 'Detail Co')
                ->where('listSlug', 'suppliers'),
        );
});

test('an entity defaults to no type (the Cheese bucket)', function () {
    expect(Entity::factory()->create()->type)->toBeNull();
});

test('the factory type states set the entity type', function () {
    expect(Entity::factory()->customer()->create()->type)->toBe('customer');
    expect(Entity::factory()->supplier()->create()->type)->toBe('supplier');
    expect(Entity::factory()->contractor()->create()->type)->toBe('contractor');
    expect(Entity::factory()->employee()->create()->type)->toBe('employee');
    expect(Entity::factory()->stateType()->create()->type)->toBe('state');
});
