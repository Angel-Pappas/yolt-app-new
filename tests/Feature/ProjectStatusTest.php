<?php

use App\Models\ProjectStatus;
use App\Models\User;

test('a crm user can view and manage project statuses', function () {
    $user = User::factory()->withCrmAccess()->create();

    $this->actingAs($user)->get('/project-statuses')->assertOk();

    $this->actingAs($user)->post('/project-statuses', ['name' => 'Scoping'])
        ->assertRedirect();

    $status = ProjectStatus::where('name', 'Scoping')->first();
    expect($status)->not->toBeNull();
    expect($status->user_id)->toBe($user->id);
});

test('a new project status appends after the last position', function () {
    $user = User::factory()->withCrmAccess()->create();
    ProjectStatus::factory()->create(['position' => 3]);

    $this->actingAs($user)->post('/project-statuses', ['name' => 'Next'])
        ->assertRedirect();

    expect(ProjectStatus::where('name', 'Next')->first()->position)->toBe(4);
});

test('a crm user can update and delete a project status', function () {
    $user = User::factory()->withCrmAccess()->create();
    $status = ProjectStatus::factory()->create(['name' => 'Old']);

    $this->actingAs($user)->patch("/project-statuses/{$status->id}", ['name' => 'New'])
        ->assertRedirect();
    expect($status->refresh()->name)->toBe('New');

    $this->actingAs($user)->delete("/project-statuses/{$status->id}")->assertRedirect();
    expect(ProjectStatus::find($status->id))->toBeNull();
});

test('a non-crm user cannot access project statuses', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/project-statuses')->assertForbidden();
    $this->actingAs($user)->post('/project-statuses', ['name' => 'X'])->assertForbidden();
});
