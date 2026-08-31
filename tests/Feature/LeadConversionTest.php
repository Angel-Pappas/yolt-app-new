<?php

use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\Project;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('converting a lead creates a linked project and flags the lead', function () {
    $user = User::factory()->withCrmAccess()->create();
    $conversion = LeadStatus::factory()->create([
        'name' => 'Converted', 'is_conversion' => true,
    ]);
    $lead = Lead::factory()->create();

    $response = $this->actingAs($user)->post("/leads/{$lead->id}/convert", [
        'name' => 'Big Project',
    ]);

    $project = Project::where('lead_id', $lead->id)->first();
    expect($project)->not->toBeNull();
    expect($project->name)->toBe('Big Project');
    expect($lead->refresh()->status_id)->toBe($conversion->id);
    $response->assertRedirect("/projects/{$project->id}");
});

test('a lead converts only once', function () {
    $user = User::factory()->withCrmAccess()->create();
    LeadStatus::factory()->create(['is_conversion' => true]);
    $lead = Lead::factory()->create();
    $existing = Project::factory()->create(['lead_id' => $lead->id]);

    $this->actingAs($user)->post("/leads/{$lead->id}/convert", ['name' => 'Dup'])
        ->assertRedirect("/projects/{$existing->id}");

    expect(Project::where('lead_id', $lead->id)->count())->toBe(1);
});

test('converting requires a project name', function () {
    $user = User::factory()->withCrmAccess()->create();
    $lead = Lead::factory()->create();

    $this->actingAs($user)->post("/leads/{$lead->id}/convert", ['name' => ''])
        ->assertSessionHasErrors('name');
});

test('converted leads are hidden from the default list', function () {
    $user = User::factory()->withCrmAccess()->create();
    $conversion = LeadStatus::factory()->create(['is_conversion' => true]);
    Lead::factory()->create(['status_id' => $conversion->id]);
    Lead::factory()->create();

    $this->actingAs($user)
        ->get('/leads')
        ->assertInertia(fn (Assert $page) => $page->has('leads', 1));
});

test('the status filter can reveal converted leads', function () {
    $user = User::factory()->withCrmAccess()->create();
    $conversion = LeadStatus::factory()->create(['is_conversion' => true]);
    Lead::factory()->create(['status_id' => $conversion->id]);
    Lead::factory()->create();

    $this->actingAs($user)
        ->get("/leads?status={$conversion->id}")
        ->assertInertia(fn (Assert $page) => $page->has('leads', 1));
});

test('the lead detail exposes an existing project', function () {
    $user = User::factory()->withCrmAccess()->create();
    $lead = Lead::factory()->create();
    $project = Project::factory()->create(['lead_id' => $lead->id]);

    $this->actingAs($user)
        ->get("/leads/{$lead->id}")
        ->assertInertia(fn (Assert $page) => $page->where('project.id', $project->id));
});

test('a non-crm user cannot convert a lead', function () {
    $user = User::factory()->create();
    $lead = Lead::factory()->create();

    $this->actingAs($user)->post("/leads/{$lead->id}/convert", ['name' => 'X'])
        ->assertForbidden();
});
