<?php

use App\Models\LeadOrigin;
use App\Models\LeadStatus;
use App\Models\ProjectStatus;
use App\Models\User;
use Database\Seeders\LeadLookupSeeder;

test('a crm user can view and manage lead statuses', function () {
    $user = User::factory()->withCrmAccess()->create();

    $this->actingAs($user)->get('/lead-statuses')->assertOk();

    $this->actingAs($user)->post('/lead-statuses', ['name' => 'Contacted'])
        ->assertRedirect();

    $status = LeadStatus::where('name', 'Contacted')->first();
    expect($status)->not->toBeNull();
    expect($status->user_id)->toBe($user->id);
});

test('a new lead status is appended after the last position', function () {
    $user = User::factory()->withCrmAccess()->create();
    LeadStatus::factory()->create(['position' => 5]);

    $this->actingAs($user)->post('/lead-statuses', ['name' => 'Next'])
        ->assertRedirect();

    expect(LeadStatus::where('name', 'Next')->first()->position)->toBe(6);
});

test('a lead status name is required', function () {
    $user = User::factory()->withCrmAccess()->create();

    $this->actingAs($user)->post('/lead-statuses', ['name' => ''])
        ->assertSessionHasErrors('name');
});

test('a crm user can update and soft-delete a lead status', function () {
    $user = User::factory()->withCrmAccess()->create();
    $status = LeadStatus::factory()->create(['name' => 'Old']);

    $this->actingAs($user)->patch("/lead-statuses/{$status->id}", ['name' => 'New'])
        ->assertRedirect();
    expect($status->refresh()->name)->toBe('New');

    $this->actingAs($user)->delete("/lead-statuses/{$status->id}")->assertRedirect();
    expect(LeadStatus::find($status->id))->toBeNull();
    expect(LeadStatus::withTrashed()->find($status->id))->not->toBeNull();
});

test('a crm user can view and manage lead origins', function () {
    $user = User::factory()->withCrmAccess()->create();

    $this->actingAs($user)->get('/lead-origins')->assertOk();

    $this->actingAs($user)->post('/lead-origins', ['name' => 'Referral'])
        ->assertRedirect();

    expect(LeadOrigin::where('name', 'Referral')->exists())->toBeTrue();
});

test('a non-crm user cannot access lead lookups', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/lead-statuses')->assertForbidden();
    $this->actingAs($user)->post('/lead-statuses', ['name' => 'X'])->assertForbidden();
    $this->actingAs($user)->get('/lead-origins')->assertForbidden();
});

test('the lookup seeder is idempotent', function () {
    $this->seed(LeadLookupSeeder::class);
    $this->seed(LeadLookupSeeder::class);

    // 6 pipeline stages + the two done-states ("Project Agreed", "Converted").
    expect(LeadStatus::count())->toBe(8);
    expect(LeadStatus::where('is_conversion', true)->count())->toBe(1);
    expect(LeadOrigin::count())->toBe(5);
    expect(ProjectStatus::count())->toBe(6);
});
