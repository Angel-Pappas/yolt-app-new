<?php

use App\Models\Entity;
use App\Models\Project;
use App\Models\User;

test('a project is created for a customer with its deliverables and opens its own page', function () {
    $customer = Entity::factory()->customer()->create();

    $response = $this->actingAs(User::factory()->create())->post('/projects', [
        'name' => 'Website', 'entity_id' => $customer->id, 'notes' => '',
        'deliverables' => [['name' => 'Design', 'net' => '1000'], ['name' => 'Build', 'net' => '2500.50']],
    ]);

    $project = Project::query()->sole();
    $response->assertRedirect("/projects/{$project->id}");
    expect($project->deliverables()->pluck('name')->all())->toBe(['Design', 'Build']);
    expect($project->load('deliverables')->totalValue())->toBe(3500.5);
});

test('a project needs a name, a customer and a deliverable', function () {
    $supplier = Entity::factory()->supplier()->create();

    $this->actingAs(User::factory()->create())
        ->post('/projects', ['name' => '', 'entity_id' => $supplier->id, 'deliverables' => []])
        ->assertSessionHasErrors(['name', 'entity_id', 'deliverables']);
});

test('editing a project replaces its deliverables', function () {
    $customer = Entity::factory()->customer()->create();
    $project = Project::factory()->create(['entity_id' => $customer->id]);
    $project->deliverables()->create(['name' => 'Old', 'net' => 10, 'position' => 0]);

    $this->actingAs(User::factory()->create())->patch("/projects/{$project->id}", [
        'name' => 'Renamed', 'entity_id' => $customer->id,
        'deliverables' => [['name' => 'New', 'net' => '50']],
    ])->assertRedirect();

    expect($project->refresh()->name)->toBe('Renamed');
    expect($project->deliverables()->pluck('net', 'name')->all())->toBe(['New' => '50.00']);
});

test('the projects list and the customer page show each project with its value', function () {
    $customer = Entity::factory()->customer()->create(['name' => 'ACME']);
    $project = Project::factory()->create(['entity_id' => $customer->id, 'name' => 'App']);
    $project->deliverables()->create(['name' => 'App', 'net' => 4000, 'position' => 0]);
    $user = User::factory()->create();

    $this->actingAs($user)->get('/projects')
        ->assertInertia(fn ($page) => $page->component('projects/index')
            ->where('projects.0.name', 'App')
            ->where('projects.0.entity.name', 'ACME')
            ->where('projects.0.value', 4000));

    $this->actingAs($user)->get("/entities/{$customer->id}")
        ->assertInertia(fn ($page) => $page->component('entities/show')
            ->where('customer.projects.0.name', 'App')
            ->where('customer.value', 4000));
});

test('deleting a project removes it', function () {
    $project = Project::factory()->create(['entity_id' => Entity::factory()->customer()->create()->id]);

    $this->actingAs(User::factory()->create())->delete("/projects/{$project->id}")->assertRedirect('/projects');

    expect(Project::query()->count())->toBe(0);
});

test('guests cannot reach projects', function () {
    $this->get('/projects')->assertRedirect('/login');
});
