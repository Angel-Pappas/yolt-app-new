<?php

use App\Models\Lead;
use App\Models\LeadAction;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('a crm user can view a lead detail page with its history', function () {
    $user = User::factory()->withCrmAccess()->create();
    $lead = Lead::factory()->create();
    LeadAction::factory()->count(2)->create(['lead_id' => $lead->id]);

    $this->actingAs($user)
        ->get("/leads/{$lead->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('leads/show')
            ->where('lead.id', $lead->id)
            ->has('actions', 2));
});

test('a crm user can log an action attributed to themselves', function () {
    $user = User::factory()->withCrmAccess()->create(['name' => 'Alex']);
    $lead = Lead::factory()->create();

    $this->actingAs($user)->post("/leads/{$lead->id}/actions", [
        'action_date' => '2026-08-20',
        'body' => 'Called the client',
    ])->assertRedirect();

    $action = LeadAction::first();
    expect($action->lead_id)->toBe($lead->id);
    expect($action->body)->toBe('Called the client');
    expect($action->author_name)->toBe('Alex');
    expect($action->user_id)->toBe($user->id);
});

test('an action requires a date and a body', function () {
    $user = User::factory()->withCrmAccess()->create();
    $lead = Lead::factory()->create();

    $this->actingAs($user)->post("/leads/{$lead->id}/actions", [
        'action_date' => '',
        'body' => '',
    ])->assertSessionHasErrors(['action_date', 'body']);
});

test('a crm user can update and delete an action', function () {
    $user = User::factory()->withCrmAccess()->create();
    $lead = Lead::factory()->create();
    $action = LeadAction::factory()->create(['lead_id' => $lead->id]);

    $this->actingAs($user)->patch("/leads/{$lead->id}/actions/{$action->id}", [
        'action_date' => '2026-08-01',
        'body' => 'Updated note',
    ])->assertRedirect();
    expect($action->refresh()->body)->toBe('Updated note');

    $this->actingAs($user)->delete("/leads/{$lead->id}/actions/{$action->id}")
        ->assertRedirect();
    expect(LeadAction::find($action->id))->toBeNull();
});

test('an action cannot be edited through the wrong lead', function () {
    $user = User::factory()->withCrmAccess()->create();
    $leadA = Lead::factory()->create();
    $leadB = Lead::factory()->create();
    $action = LeadAction::factory()->create(['lead_id' => $leadA->id]);

    $this->actingAs($user)->patch("/leads/{$leadB->id}/actions/{$action->id}", [
        'action_date' => '2026-08-01',
        'body' => 'x',
    ])->assertNotFound();
});

test('a non-crm user cannot access lead actions', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->create();

    $this->actingAs($user)->get("/leads/{$lead->id}")->assertForbidden();
    $this->actingAs($user)->post("/leads/{$lead->id}/actions", [
        'action_date' => '2026-08-01', 'body' => 'x',
    ])->assertForbidden();
});
