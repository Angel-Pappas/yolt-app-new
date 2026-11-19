<?php

use App\Models\Category;
use App\Models\Entity;
use App\Models\LeaveEntry;
use App\Models\PublicHoliday;
use App\Models\Recurrence;
use App\Models\RecurrenceEntry;
use App\Models\User;
use App\Support\AmountLines;
use App\Support\Leave;
use App\Support\Termination;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-11-12');
});

/** An employee hired on a date, with a monthly gross salary. */
function hired(string $hireDate, float $gross = 1200, array $profile = []): Entity
{
    $employee = Entity::factory()->create(['type' => 'employee']);
    $employee->employeeProfile()->create(array_merge(['hire_date' => $hireDate, 'children' => 0], $profile));
    $recurrence = Recurrence::factory()->create([
        'type' => 'expense', 'entity_id' => $employee->id, 'is_payroll' => true,
        'category_id' => Category::query()->firstOrCreate(['type' => 'expense', 'name' => AmountLines::PAYROLL_CATEGORY])->id,
        'interval_unit' => 'month', 'interval_count' => 1, 'day_of_month' => 28,
        'start_date' => $hireDate, 'end_date' => null,
    ]);
    RecurrenceEntry::factory()->create(['recurrence_id' => $recurrence->id, 'start_date' => $hireDate, 'end_date' => null, 'net' => $gross]);

    return $employee->fresh();
}

test('leave days by law: pro rata, then 21, 22, and more with long service', function () {
    $profile = hired('2026-07-01')->employeeProfile;
    expect(Leave::entitlement($profile, 2025))->toBe(0);
    expect(Leave::entitlement($profile, 2026))->toBe(10);  // 6 of 12 months × 20
    expect(Leave::entitlement($profile, 2027))->toBe(21);
    expect(Leave::entitlement($profile, 2028))->toBe(22);
    expect(Leave::entitlement($profile, 2036))->toBe(25);  // 10 years here
    expect(Leave::entitlement($profile, 2051))->toBe(26);  // 25 years

    // Work elsewhere counts toward the 12 and 25 years.
    expect(Leave::entitlement(hired('2024-01-01', 1200, ['prior_service_years' => 10])->employeeProfile, 2026))->toBe(25);
});

test('a six-day week and an override change the leave days', function () {
    expect(Leave::entitlement(hired('2020-01-01', 1200, ['work_days_per_week' => 6])->employeeProfile, 2026))->toBe(26);
    expect(Leave::entitlement(hired('2020-01-01', 1200, ['leave_days_override' => 30])->employeeProfile, 2026))->toBe(30);
});

test('leave days are counted from the dates, skipping weekends and holidays', function () {
    PublicHoliday::factory()->create(['date' => '2026-12-25']);
    $employee = hired('2020-01-01');

    // Mon 21 Dec – Fri 1 Jan: 10 weekdays, less the 25th.
    $this->actingAs(User::factory()->create())
        ->post("/entities/{$employee->id}/leave", ['start_date' => '2026-12-21', 'end_date' => '2027-01-01'])
        ->assertRedirect();

    expect(LeaveEntry::sole()->days)->toBe('9.0');
    expect(Leave::summary($employee, 2026))->toBe(['year' => 2026, 'entitled' => 22, 'taken' => 9.0, 'remaining' => 13.0]);
});

test('typed days win, and leave belongs to its employee', function () {
    $employee = hired('2020-01-01');
    $other = hired('2020-01-01');
    $user = User::factory()->create();

    $this->actingAs($user)->post("/entities/{$employee->id}/leave", ['start_date' => '2026-06-01', 'end_date' => '2026-06-01', 'days' => '0.5']);
    $leave = LeaveEntry::sole();
    expect($leave->days)->toBe('0.5');

    $this->actingAs($user)->delete("/entities/{$other->id}/leave/{$leave->id}")->assertNotFound();
    $this->actingAs($user)->delete("/entities/{$employee->id}/leave/{$leave->id}")->assertRedirect();
    expect(LeaveEntry::count())->toBe(0);
});

test('severance months and notice by completed years', function () {
    expect(array_map(Termination::severanceMonths(...), [0, 1, 3, 4, 5, 6, 8, 9, 10, 11, 15, 16, 30]))
        ->toBe([0, 2, 2, 3, 3, 4, 5, 5, 6, 7, 11, 12, 12]);
    expect(array_map(Termination::noticeMonths(...), [0, 1, 2, 4, 5, 9, 10, 20]))
        ->toBe([0, 1, 2, 2, 3, 3, 4, 4]);
});

test('dismissing after five years: severance, unused leave and the Christmas bonus so far', function () {
    $employee = hired('2021-03-01', 1200);
    LeaveEntry::forceCreate(['entity_id' => $employee->id, 'start_date' => '2026-08-03', 'end_date' => '2026-08-14', 'days' => 10]);

    $e = Termination::estimate($employee, '2026-11-12');

    expect($e['years'])->toBe(5);
    expect($e['gross'])->toBe(1200.0);
    expect($e['notice_months'])->toBe(3);
    $lines = collect($e['lines'])->pluck('amount', 'label');
    // 3 months × 1.200 × 7/6.
    expect($lines['Severance'])->toBe(4200.0);
    // 22 − 10 days × 1.200 ÷ 25.
    expect($lines['Unused leave'])->toBe(576.0);
    // 1 May – 12 Nov = 196 days of 237,5 × 1.200 × 25/24.
    expect($lines['Christmas bonus'])->toBe(round(1200 * 25 / 24 * 196 / 237.5, 2));
    // The leave allowance was paid in June.
    expect($lines->has('Leave allowance'))->toBeFalse();
    expect($e['total'])->toBe(round(4200 + 576 + $lines['Christmas bonus'], 2));
});

test('with notice the severance is halved; under a year there is none', function () {
    expect(collect(Termination::estimate(hired('2021-03-01', 1200), '2026-11-12', true)['lines'])->firstWhere('label', 'Severance')['amount'])->toBe(2100.0);
    expect(collect(Termination::estimate(hired('2026-06-01', 1200), '2026-11-12')['lines'])->firstWhere('label', 'Severance')['amount'])->toBe(0.0);
});

test('pay above the cap is not counted for severance', function () {
    // Cap: 8 × 30 × 41,09 = 9.861,60.
    $e = Termination::estimate(hired('2024-01-01', 12000), '2026-11-12');

    expect(collect($e['lines'])->firstWhere('label', 'Severance')['amount'])->toBe(round(2 * 9861.60 * 7 / 6, 2));
});

test('early in the year the leave allowance and Easter bonus are still owed', function () {
    $e = Termination::estimate(hired('2021-03-01', 1200), '2027-02-28');
    $lines = collect($e['lines'])->pluck('amount', 'label');

    // 59 days of 365 × ½ month; 59 of 120 days × ½ month.
    expect($lines['Leave allowance'])->toBe(round(600 * 59 / 365, 2));
    expect($lines['Easter bonus'])->toBe(round(600 * 59 / 120, 2));
});

test('the termination estimate is served to the employee page', function () {
    $employee = hired('2021-03-01', 1200);

    $this->actingAs(User::factory()->create())
        ->getJson("/entities/{$employee->id}/termination?date=2026-11-12&notice=1")
        ->assertOk()
        ->assertJsonPath('years', 5)
        ->assertJsonPath('lines.0.amount', 2100);
});
