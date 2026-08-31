<?php

use App\Models\Lead;
use App\Models\LeadOrigin;
use App\Models\LeadStatus;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('a crm user can view leads', function () {
    $user = User::factory()->withCrmAccess()->create();
    Lead::factory()->count(3)->create();

    $this->actingAs($user)
        ->get('/leads')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('leads/index')
            ->has('leads', 3));
});

test('a crm user can add a lead with an auto-assigned number', function () {
    $user = User::factory()->withCrmAccess()->create();
    Lead::factory()->create(['sort_order' => 47]);
    $origin = LeadOrigin::factory()->create();
    $status = LeadStatus::factory()->create();

    $this->actingAs($user)->post('/leads', [
        'name' => 'Acme Co',
        'origin_id' => $origin->id,
        'status_id' => $status->id,
        'contact_email' => 'contact@example.com',
    ])->assertRedirect();

    $lead = Lead::where('name', 'Acme Co')->first();
    expect($lead)->not->toBeNull();
    expect($lead->sort_order)->toBe(48);
    expect($lead->user_id)->toBe($user->id);
    expect($lead->origin_id)->toBe($origin->id);
});

test('a lead number is never reused after a soft delete', function () {
    $user = User::factory()->withCrmAccess()->create();
    $lead = Lead::factory()->create(['sort_order' => 10]);
    $lead->delete();

    $this->actingAs($user)->post('/leads', ['name' => 'Next'])
        ->assertRedirect();

    // max(sort_order) still counts the soft-deleted row, so the next is 11.
    expect(Lead::where('name', 'Next')->first()->sort_order)->toBe(11);
});

test('a lead name is required', function () {
    $user = User::factory()->withCrmAccess()->create();

    $this->actingAs($user)->post('/leads', ['name' => ''])
        ->assertSessionHasErrors('name');
});

test('a crm user can update and soft-delete a lead', function () {
    $user = User::factory()->withCrmAccess()->create();
    $lead = Lead::factory()->create(['name' => 'Old']);

    $this->actingAs($user)->patch("/leads/{$lead->id}", [
        'name' => 'New',
        'next_step' => 'Call back',
    ])->assertRedirect();
    expect($lead->refresh()->name)->toBe('New');
    expect($lead->next_step)->toBe('Call back');

    $this->actingAs($user)->delete("/leads/{$lead->id}")->assertRedirect();
    expect(Lead::find($lead->id))->toBeNull();
    expect(Lead::withTrashed()->find($lead->id))->not->toBeNull();
});

test('leads can be filtered by status and searched', function () {
    $user = User::factory()->withCrmAccess()->create();
    $status = LeadStatus::factory()->create();
    Lead::factory()->create(['name' => 'Alpha', 'status_id' => $status->id]);
    Lead::factory()->create(['name' => 'Beta']);

    $this->actingAs($user)
        ->get("/leads?status={$status->id}")
        ->assertInertia(fn (Assert $page) => $page->has('leads', 1));

    $this->actingAs($user)
        ->get('/leads?q=Alpha')
        ->assertInertia(fn (Assert $page) => $page->has('leads', 1));
});

test('a lead can carry campaign fields', function () {
    $user = User::factory()->withCrmAccess()->create();

    $this->actingAs($user)->post('/leads', [
        'name' => 'Campaign lead',
        'campaign_platform' => 'facebook',
        'campaign_we_are' => 'A bakery',
        'campaign_we_want' => 'More orders',
    ])->assertRedirect();

    $lead = Lead::where('name', 'Campaign lead')->first();
    expect($lead->campaign_platform)->toBe('facebook');
    expect($lead->campaign_we_are)->toBe('A bakery');
    expect($lead->campaign_we_want)->toBe('More orders');
});

test('an invalid campaign platform is rejected', function () {
    $user = User::factory()->withCrmAccess()->create();

    $this->actingAs($user)->post('/leads', [
        'name' => 'X',
        'campaign_platform' => 'tiktok',
    ])->assertSessionHasErrors('campaign_platform');
});

test('a non-crm user cannot access leads', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/leads')->assertForbidden();
    $this->actingAs($user)->post('/leads', ['name' => 'X'])->assertForbidden();
});
