<?php

use App\Models\TaxRule;
use App\Models\User;
use App\Support\TaxRules;

test('the app starts with the 2025 and 2026 law', function () {
    expect(TaxRule::query()->orderBy('valid_from')->pluck('valid_from')->map->format('Y-m-d')->all())
        ->toBe(['2025-01-01', '2026-01-01']);
    expect(TaxRules::at('2025-06-01')->validFrom)->toBe('2025-01-01');
    expect(TaxRules::at('2026-06-01')->validFrom)->toBe('2026-01-01');
    // Before the first set, the first set applies.
    expect(TaxRules::at('2020-01-01')->validFrom)->toBe('2025-01-01');
});

test('a new set starts as a copy of the one in force before its date', function () {
    $this->actingAs(User::factory()->create())
        ->post('/configuration/tax-rules', ['valid_from' => '2027-01-01', 'note' => 'Budget 2027'])
        ->assertRedirect();

    $new = TaxRule::query()->whereDate('valid_from', '2027-01-01')->sole();
    expect($new->data)->toEqual(TaxRule::query()->whereDate('valid_from', '2026-01-01')->sole()->data);
    expect(TaxRules::at('2027-03-01')->validFrom)->toBe('2027-01-01');
});

test('two sets cannot start on the same date', function () {
    $this->actingAs(User::factory()->create())
        ->post('/configuration/tax-rules', ['valid_from' => '2026-01-01'])
        ->assertSessionHasErrors('valid_from');
});

test('a set is edited and stored as numbers', function () {
    $set = TaxRule::query()->whereDate('valid_from', '2026-01-01')->sole();
    $data = $set->data;
    $data['efka']['employee_rate'] = '12.5';
    $data['company_tax']['rate'] = '20';

    $this->actingAs(User::factory()->create())
        ->patch("/configuration/tax-rules/{$set->id}", ['valid_from' => '2026-01-01', 'note' => null, 'data' => $data])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $rules = TaxRules::at('2026-06-01');
    expect($rules->number('efka.employee_rate'))->toBe(12.5);
    expect($rules->number('company_tax.rate'))->toBe(20.0);
    // The open-ended last bracket stays open-ended.
    expect(collect($rules->brackets())->last()['up_to'])->toBeNull();
});

test('a broken set is rejected', function () {
    $set = TaxRule::query()->first();
    $data = $set->data;
    $data['efka']['employee_rate'] = 'lots';

    $this->actingAs(User::factory()->create())
        ->patch("/configuration/tax-rules/{$set->id}", ['valid_from' => '2025-01-01', 'data' => $data])
        ->assertSessionHasErrors('data.efka.employee_rate');
});

test('the last set cannot be deleted', function () {
    $user = User::factory()->create();
    $sets = TaxRule::query()->orderBy('valid_from')->get();

    $this->actingAs($user)->delete("/configuration/tax-rules/{$sets[0]->id}")->assertRedirect();
    $this->actingAs($user)->delete("/configuration/tax-rules/{$sets[1]->id}")->assertStatus(422);
    expect(TaxRule::count())->toBe(1);
});
