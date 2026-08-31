<?php

use App\Models\Project;
use App\Models\ProjectAction;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('a crm user can view a project detail page with its history', function () {
    $user = User::factory()->withCrmAccess()->create();
    $project = Project::factory()->create();
    ProjectAction::factory()->count(2)->create(['project_id' => $project->id]);

    $this->actingAs($user)
        ->get("/projects/{$project->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/show')
            ->where('project.id', $project->id)
            ->has('actions', 2));
});

test('a crm user can log a project action attributed to themselves', function () {
    $user = User::factory()->withCrmAccess()->create(['name' => 'Sam']);
    $project = Project::factory()->create();

    $this->actingAs($user)->post("/projects/{$project->id}/actions", [
        'action_date' => '2026-08-20',
        'body' => 'Kickoff meeting',
    ])->assertRedirect();

    $action = ProjectAction::first();
    expect($action->project_id)->toBe($project->id);
    expect($action->body)->toBe('Kickoff meeting');
    expect($action->author_name)->toBe('Sam');
});

test('a project action requires a date and a body', function () {
    $user = User::factory()->withCrmAccess()->create();
    $project = Project::factory()->create();

    $this->actingAs($user)->post("/projects/{$project->id}/actions", [
        'action_date' => '', 'body' => '',
    ])->assertSessionHasErrors(['action_date', 'body']);
});

test('a crm user can update and delete a project action', function () {
    $user = User::factory()->withCrmAccess()->create();
    $project = Project::factory()->create();
    $action = ProjectAction::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)->patch("/projects/{$project->id}/actions/{$action->id}", [
        'action_date' => '2026-08-01', 'body' => 'Updated',
    ])->assertRedirect();
    expect($action->refresh()->body)->toBe('Updated');

    $this->actingAs($user)->delete("/projects/{$project->id}/actions/{$action->id}")
        ->assertRedirect();
    expect(ProjectAction::find($action->id))->toBeNull();
});

test('a project action cannot be edited through the wrong project', function () {
    $user = User::factory()->withCrmAccess()->create();
    $a = Project::factory()->create();
    $b = Project::factory()->create();
    $action = ProjectAction::factory()->create(['project_id' => $a->id]);

    $this->actingAs($user)->patch("/projects/{$b->id}/actions/{$action->id}", [
        'action_date' => '2026-08-01', 'body' => 'x',
    ])->assertNotFound();
});

test('a non-crm user cannot access project actions', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    $this->actingAs($user)->get("/projects/{$project->id}")->assertForbidden();
    $this->actingAs($user)->post("/projects/{$project->id}/actions", [
        'action_date' => '2026-08-01', 'body' => 'x',
    ])->assertForbidden();
});
