<?php

use App\Models\Category;
use App\Models\Entity;
use App\Models\Recurrence;
use App\Models\RecurrenceEntry;
use App\Models\Transaction;
use App\Models\User;
use App\Support\AmountLines;
use App\Support\RecurrenceGenerator;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-11-12');
});

/** An employee's monthly payroll recurrence with one period of 920 gross. */
function autoPayrollRecurrence(bool $auto, string $start = '2026-10-01', ?string $end = '2026-11-30'): Recurrence
{
    $employee = Entity::factory()->create(['type' => 'employee']);
    $employee->employeeProfile()->create(['birth_date' => '1984-06-05', 'children' => 0]);
    $recurrence = Recurrence::factory()->create([
        'type' => 'expense',
        'entity_id' => $employee->id,
        'category_id' => Category::factory()->create(['type' => 'expense', 'name' => AmountLines::PAYROLL_CATEGORY])->id,
        'is_payroll' => true,
        'interval_unit' => 'month', 'interval_count' => 1, 'day_of_month' => 28,
        'start_date' => $start, 'end_date' => $end,
    ]);
    RecurrenceEntry::factory()->create([
        'recurrence_id' => $recurrence->id, 'start_date' => $start, 'end_date' => $end,
        'net' => 920, 'fmy_amount' => 1, 'efka_employee_amount' => 2, 'efka_employer_amount' => 3,
        'auto_payroll' => $auto,
    ]);

    return $recurrence->fresh();
}

/** The salary rows only (no bonuses). */
function salaryRows(Recurrence $r)
{
    return Transaction::query()->where('recurrence_id', $r->id)->where('managed_key', 'not like', '%bonus%')->orderBy('date')->get();
}

test('an automatic payroll period is calculated from the gross', function () {
    $recurrence = autoPayrollRecurrence(true);
    RecurrenceGenerator::sync($recurrence);

    $row = salaryRows($recurrence)->first();
    expect($row->net)->toBe('920.00');
    expect($row->efka_employee_amount)->toBe('123.00');
    expect($row->efka_employer_amount)->toBe('200.47');
    expect($row->fmy_amount)->toBe('25.33');
});

test('a manual payroll period keeps its typed figures', function () {
    $recurrence = autoPayrollRecurrence(false);
    RecurrenceGenerator::sync($recurrence);

    $row = salaryRows($recurrence)->first();
    expect([$row->fmy_amount, $row->efka_employee_amount, $row->efka_employer_amount])->toBe(['1.00', '2.00', '3.00']);
});

test('changing the employee details recalculates the automatic rows', function () {
    $recurrence = autoPayrollRecurrence(true);
    RecurrenceGenerator::sync($recurrence);

    // Four children: no tax on this income.
    $this->actingAs(User::factory()->create())
        ->patch("/entities/{$recurrence->entity_id}/employee", [
            'birth_date' => '1984-06-05', 'children' => 4, 'prior_service_years' => 0, 'work_days_per_week' => 5,
        ])->assertRedirect();

    expect(salaryRows($recurrence)->first()->fmy_amount)->toBe('0.00');
});

test('an automatic bonus is calculated on its own amount', function () {
    $recurrence = autoPayrollRecurrence(true, '2026-01-01', null);
    RecurrenceGenerator::sync($recurrence);

    // Christmas 2026: a full bonus of 920 × 25/24 = 958,33.
    $bonus = Transaction::query()->where('managed_key', "recurrence:{$recurrence->id}:bonus-christmas:2026")->sole();
    expect($bonus->net)->toBe('958.33');
    expect($bonus->efka_employee_amount)->toBe('128.13');
    // (958,33 − 128,13) × 14 = 11.622,80 → 900 + 324,56 − 777 = 447,56 → 31,97.
    expect($bonus->fmy_amount)->toBe('31.97');
});

test('the payroll preview answers with the same calculation', function () {
    $employee = Entity::factory()->create(['type' => 'employee']);
    $employee->employeeProfile()->create(['birth_date' => '1999-05-08', 'children' => 0]);

    $this->actingAs(User::factory()->create())
        ->getJson("/payroll/preview?gross=1462.18&entity_id={$employee->id}&date=2026-05-29")
        ->assertOk()
        ->assertJson(['fmy' => 66.69, 'efka_employee' => 195.49, 'to_pay' => 1200]);
});

test('only an employee has employee details', function () {
    $supplier = Entity::factory()->create(['type' => 'supplier']);

    $this->actingAs(User::factory()->create())
        ->patch("/entities/{$supplier->id}/employee", ['children' => 0, 'prior_service_years' => 0, 'work_days_per_week' => 5])
        ->assertNotFound();
});
