<?php

use App\Models\Lead;
use App\Models\LeadContact;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the lead detail page includes its contacts', function () {
    $user = User::factory()->withCrmAccess()->create();
    $lead = Lead::factory()->create();
    LeadContact::factory()->count(2)->create(['lead_id' => $lead->id]);

    $this->actingAs($user)
        ->get("/leads/{$lead->id}")
        ->assertInertia(fn (Assert $page) => $page->has('contacts', 2));
});

test('a crm user can add a contact to a lead', function () {
    $user = User::factory()->withCrmAccess()->create();
    $lead = Lead::factory()->create();

    $this->actingAs($user)->post("/leads/{$lead->id}/contacts", [
        'name' => 'Jordan',
        'position' => 'CTO',
        'email' => 'jordan@example.com',
    ])->assertRedirect();

    $contact = LeadContact::first();
    expect($contact->lead_id)->toBe($lead->id);
    expect($contact->name)->toBe('Jordan');
    expect($contact->user_id)->toBe($user->id);
});

test('a contact requires a name', function () {
    $user = User::factory()->withCrmAccess()->create();
    $lead = Lead::factory()->create();

    $this->actingAs($user)->post("/leads/{$lead->id}/contacts", ['name' => ''])
        ->assertSessionHasErrors('name');
});

test('a crm user can update and delete a contact', function () {
    $user = User::factory()->withCrmAccess()->create();
    $lead = Lead::factory()->create();
    $contact = LeadContact::factory()->create(['lead_id' => $lead->id]);

    $this->actingAs($user)->patch("/leads/{$lead->id}/contacts/{$contact->id}", [
        'name' => 'Renamed',
    ])->assertRedirect();
    expect($contact->refresh()->name)->toBe('Renamed');

    $this->actingAs($user)->delete("/leads/{$lead->id}/contacts/{$contact->id}")
        ->assertRedirect();
    expect(LeadContact::find($contact->id))->toBeNull();
});

test('a contact cannot be edited through the wrong lead', function () {
    $user = User::factory()->withCrmAccess()->create();
    $leadA = Lead::factory()->create();
    $leadB = Lead::factory()->create();
    $contact = LeadContact::factory()->create(['lead_id' => $leadA->id]);

    $this->actingAs($user)->patch("/leads/{$leadB->id}/contacts/{$contact->id}", [
        'name' => 'x',
    ])->assertNotFound();
});

test('a non-crm user cannot manage contacts', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->create();

    $this->actingAs($user)->post("/leads/{$lead->id}/contacts", ['name' => 'X'])
        ->assertForbidden();
});
