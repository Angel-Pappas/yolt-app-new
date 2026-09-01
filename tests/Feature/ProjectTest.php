<?php

use App\Models\Lead;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('a crm user can view projects', function () {
    $user = User::factory()->withCrmAccess()->create();
    Project::factory()->count(2)->create();

    $this->actingAs($user)
        ->get('/projects')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/index')
            ->has('projects', 2));
});

test('a crm user can add a project with an auto number', function () {
    $user = User::factory()->withCrmAccess()->create();
    Project::factory()->create(['sort_order' => 12]);
    $status = ProjectStatus::factory()->create();

    $this->actingAs($user)->post('/projects', [
        'name' => 'Website rebuild',
        'status_id' => $status->id,
        'value' => '15000',
        'estimated_months' => '4',
    ])->assertRedirect();

    $project = Project::where('name', 'Website rebuild')->first();
    expect($project)->not->toBeNull();
    expect($project->sort_order)->toBe(13);
    expect($project->value)->toBe('15000.00');
    expect($project->estimated_months)->toBe(4);
    expect($project->user_id)->toBe($user->id);
});

test('a project number is never reused after a soft delete', function () {
    $user = User::factory()->withCrmAccess()->create();
    Project::factory()->create(['sort_order' => 20])->delete();

    $this->actingAs($user)->post('/projects', ['name' => 'Next'])
        ->assertRedirect();

    expect(Project::where('name', 'Next')->first()->sort_order)->toBe(21);
});

test('a project surfaces the linked lead contact as client', function () {
    $user = User::factory()->withCrmAccess()->create();
    $lead = Lead::factory()->create(['contact_name' => 'Dana Ray']);
    Project::factory()->create(['lead_id' => $lead->id]);

    $this->actingAs($user)
        ->get('/projects')
        ->assertInertia(fn (Assert $page) => $page
            ->where('projects.0.lead.contact_name', 'Dana Ray'));
});

test('a project name is required and value must be numeric', function () {
    $user = User::factory()->withCrmAccess()->create();

    $this->actingAs($user)->post('/projects', ['name' => '', 'value' => 'abc'])
        ->assertSessionHasErrors(['name', 'value']);
});

test('a crm user can update and soft-delete a project', function () {
    $user = User::factory()->withCrmAccess()->create();
    $project = Project::factory()->create(['name' => 'Old']);

    $this->actingAs($user)->patch("/projects/{$project->id}", ['name' => 'New'])
        ->assertRedirect();
    expect($project->refresh()->name)->toBe('New');

    $this->actingAs($user)->delete("/projects/{$project->id}")->assertRedirect();
    expect(Project::find($project->id))->toBeNull();
    expect(Project::withTrashed()->find($project->id))->not->toBeNull();
});

test('projects can be filtered by status and searched', function () {
    $user = User::factory()->withCrmAccess()->create();
    $status = ProjectStatus::factory()->create();
    Project::factory()->create(['name' => 'Alpha', 'status_id' => $status->id]);
    Project::factory()->create(['name' => 'Beta']);

    $this->actingAs($user)
        ->get("/projects?status={$status->id}")
        ->assertInertia(fn (Assert $page) => $page->has('projects', 1));

    $this->actingAs($user)
        ->get('/projects?q=Alpha')
        ->assertInertia(fn (Assert $page) => $page->has('projects', 1));
});

test('a crm user can inline-edit a project next step and status', function () {
    $user = User::factory()->withCrmAccess()->create();
    $status = ProjectStatus::factory()->create();
    $project = Project::factory()->create();

    $this->actingAs($user)->patch("/projects/{$project->id}/next-step", [
        'next_step' => 'Scope it',
    ])->assertRedirect();
    expect($project->refresh()->next_step)->toBe('Scope it');

    $this->actingAs($user)->patch("/projects/{$project->id}/status", [
        'status_id' => $status->id,
    ])->assertRedirect();
    expect($project->refresh()->status_id)->toBe($status->id);
});

test('a non-crm user cannot access projects', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/projects')->assertForbidden();
    $this->actingAs($user)->post('/projects', ['name' => 'X'])->assertForbidden();
});
