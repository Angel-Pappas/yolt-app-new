<?php

use App\Models\PublicHoliday;
use App\Models\User;

test('an active user can view public holidays', function () {
    $user = User::factory()->create();
    PublicHoliday::factory()->count(2)->create();

    $this->actingAs($user)->get('/configuration/public-holidays')->assertOk();
});

test('a guest cannot view public holidays', function () {
    $this->get('/configuration/public-holidays')->assertRedirect(route('login'));
});

test('an active user can create a public holiday', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/configuration/public-holidays', [
        'date' => '2026-03-25',
        'name' => 'Independence Day',
    ])->assertRedirect();

    $holiday = PublicHoliday::where('name', 'Independence Day')->first();
    expect($holiday)->not->toBeNull();
    expect($holiday->date->format('Y-m-d'))->toBe('2026-03-25');
    expect($holiday->user_id)->toBe($user->id);
});

test('a public holiday requires a valid date', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/configuration/public-holidays', [
        'date' => 'not-a-date',
    ])->assertSessionHasErrors('date');
});

test('the same active date cannot be listed twice', function () {
    $user = User::factory()->create();
    PublicHoliday::factory()->create(['date' => '2026-05-01']);

    $this->actingAs($user)->post('/configuration/public-holidays', [
        'date' => '2026-05-01',
    ])->assertSessionHasErrors('date');
});

test('an active user can soft-delete a public holiday', function () {
    $user = User::factory()->create();
    $holiday = PublicHoliday::factory()->create();

    $this->actingAs($user)
        ->delete("/configuration/public-holidays/{$holiday->id}")
        ->assertRedirect();

    expect(PublicHoliday::find($holiday->id))->toBeNull();
    expect(PublicHoliday::withTrashed()->find($holiday->id))->not->toBeNull();
});

test('a guest cannot create a public holiday', function () {
    $this->post('/configuration/public-holidays', [
        'date' => '2026-05-01',
    ])->assertRedirect(route('login'));
});
