<?php

use App\Models\Entity;
use App\Models\PublicHoliday;
use App\Models\Recurrence;
use App\Models\RecurrenceEntry;
use App\Models\Setting;
use App\Models\Transaction;
use App\Support\EfkaLedger;
use App\Support\FmyLedger;
use App\Support\PayrollBonuses;
use App\Support\RecurrenceGenerator;
use Illuminate\Support\Carbon;

beforeEach(function () {
    // Horizon = 2028-10-23, so 2027 is a whole year in view.
    Carbon::setTestNow('2026-10-23');
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * An employee's monthly payroll recurrence with the given periods — each
 * [start, end|null, net, fmy, efka employee, efka employer].
 *
 * @param  list<array{0: string, 1: string|null, 2: float|int, 3?: float|int, 4?: float|int, 5?: float|int}>  $periods
 */
function payroll(array $periods, string $entityType = 'employee'): Recurrence
{
    $recurrence = Recurrence::factory()->create([
        'entity_id' => Entity::factory()->create(['type' => $entityType])->id,
        'description' => 'Salary',
        'is_payroll' => true,
        'interval_unit' => 'month',
        'day_of_month' => 1,
        'start_date' => $periods[0][0],
        'end_date' => end($periods)[1],
    ]);
    foreach ($periods as $i => $p) {
        RecurrenceEntry::factory()->create([
            'recurrence_id' => $recurrence->id,
            'start_date' => $p[0],
            'end_date' => $p[1],
            'net' => $p[2],
            'fmy_amount' => $p[3] ?? null,
            'efka_employee_amount' => $p[4] ?? null,
            'efka_employer_amount' => $p[5] ?? null,
            'position' => $i,
        ]);
    }

    return $recurrence;
}

function bonusRow(Recurrence $recurrence, string $kind, int $year): ?Transaction
{
    return Transaction::where('managed_key', "recurrence:{$recurrence->id}:bonus-{$kind}:{$year}")->first();
}

test('Orthodox Easter falls on the published dates', function (int $year, string $date) {
    expect(PayrollBonuses::orthodoxEaster($year)->toDateString())->toBe($date);
})->with([
    [2024, '2024-05-05'], [2025, '2025-04-20'], [2026, '2026-04-12'], [2027, '2027-05-02'],
    [2028, '2028-04-16'], [2029, '2029-04-08'], [2030, '2030-04-28'], [2031, '2031-04-13'],
    [2032, '2032-05-02'], [2033, '2033-04-24'], [2034, '2034-04-09'], [2035, '2035-04-29'],
]);

test('the usual pay days: Friday before Holy Monday, 20 December, end of June', function () {
    expect(PayrollBonuses::dueDate('easter', 2026, [])->toDateString())->toBe('2026-04-03');
    expect(PayrollBonuses::dueDate('easter', 2027, [])->toDateString())->toBe('2027-04-23');
    // 20 Dec 2026 is a Sunday → Friday 18; 2027 is a Monday and stays.
    expect(PayrollBonuses::dueDate('christmas', 2026, [])->toDateString())->toBe('2026-12-18');
    expect(PayrollBonuses::dueDate('christmas', 2027, [])->toDateString())->toBe('2027-12-20');
    expect(PayrollBonuses::dueDate('leave', 2026, [])->toDateString())->toBe('2026-06-30');
    // 30 Jun 2029 is a Saturday → Friday 29.
    expect(PayrollBonuses::dueDate('leave', 2029, [])->toDateString())->toBe('2029-06-29');
});

test('a pay day on a public holiday moves to the previous working day', function () {
    $holidays = ['2027-04-23' => true, '2027-12-20' => true];

    expect(PayrollBonuses::dueDate('easter', 2027, $holidays)->toDateString())->toBe('2027-04-22');
    // Back past the holiday and the weekend to Friday 17.
    expect(PayrollBonuses::dueDate('christmas', 2027, $holidays)->toDateString())->toBe('2027-12-17');
});

test('a full year earns the full bonuses, with FMY and EFKA scaled alike', function () {
    $recurrence = payroll([['2026-01-01', null, 1000, 80, 150, 250]]);

    RecurrenceGenerator::sync($recurrence);

    $easter = bonusRow($recurrence, 'easter', 2027);
    expect($easter->description)->toBe('Easter bonus');
    expect($easter->date->toDateString())->toBe('2027-04-23');
    expect([(float) $easter->net, (float) $easter->fmy_amount, (float) $easter->efka_employee_amount, (float) $easter->efka_employer_amount])
        ->toBe([500.0, 40.0, 75.0, 125.0]);

    $leave = bonusRow($recurrence, 'leave', 2027);
    expect($leave->description)->toBe('Leave allowance');
    expect($leave->date->toDateString())->toBe('2027-06-30');
    expect((float) $leave->net)->toBe(500.0);

    // Christmas carries the 1,041666 leave-allowance share.
    $christmas = bonusRow($recurrence, 'christmas', 2027);
    expect($christmas->description)->toBe('Christmas bonus');
    expect($christmas->date->toDateString())->toBe('2027-12-20');
    expect([(float) $christmas->net, (float) $christmas->fmy_amount, (float) $christmas->efka_employee_amount, (float) $christmas->efka_employer_amount])
        ->toBe([1041.67, 83.33, 156.25, 260.42]);
});

test('a February start pro-rates Easter and leave, but Christmas stays full', function () {
    $recurrence = payroll([['2027-02-02', null, 1000]]);

    RecurrenceGenerator::sync($recurrence);

    // Easter: 88 days of 1 Jan–30 Apr → 88/120 of ½ salary.
    expect((float) bonusRow($recurrence, 'easter', 2027)->net)->toBe(366.67);
    // Leave: 333 of 365 days → 333/365 of ½ salary.
    expect((float) bonusRow($recurrence, 'leave', 2027)->net)->toBe(456.16);
    expect((float) bonusRow($recurrence, 'christmas', 2027)->net)->toBe(1041.67);
});

test('a raise part-way counts each period for the days it covers', function () {
    $recurrence = payroll([
        ['2027-01-01', '2027-06-30', 1000],
        ['2027-07-01', null, 1500],
    ]);

    RecurrenceGenerator::sync($recurrence);

    // Easter accrues only on the 1,000 period.
    expect((float) bonusRow($recurrence, 'easter', 2027)->net)->toBe(500.0);
    // Leave: (181 × 1,000 + 184 × 1,500) / 365 × ½.
    expect((float) bonusRow($recurrence, 'leave', 2027)->net)->toBe(626.03);
    // Christmas: (61 × 1,000 + 184 × 1,500) / 245 × 25/24.
    expect((float) bonusRow($recurrence, 'christmas', 2027)->net)->toBe(1432.82);
});

test('leaving early cuts the bonuses and pays them with the last salary', function () {
    $recurrence = payroll([['2027-01-01', '2027-03-31', 1000]]);

    RecurrenceGenerator::sync($recurrence);

    $easter = bonusRow($recurrence, 'easter', 2027);
    expect((float) $easter->net)->toBe(375.0); // 90/120 of 500
    expect($easter->date->toDateString())->toBe('2027-03-31');
    $leave = bonusRow($recurrence, 'leave', 2027);
    expect((float) $leave->net)->toBe(123.29); // 90/365 of 500
    expect($leave->date->toDateString())->toBe('2027-03-31');
    expect(bonusRow($recurrence, 'christmas', 2027))->toBeNull();
});

test('shortening the end date lowers bonuses already generated', function () {
    $recurrence = payroll([['2027-01-01', null, 1000]]);
    RecurrenceGenerator::sync($recurrence);
    expect((float) bonusRow($recurrence, 'christmas', 2027)->net)->toBe(1041.67);

    $recurrence->entries()->update(['end_date' => '2027-08-31']);
    $recurrence->update(['end_date' => '2027-08-31']);
    RecurrenceGenerator::sync($recurrence->fresh());

    // 123 days of May–Aug: 123/237.5 × 1,041.67.
    $christmas = bonusRow($recurrence, 'christmas', 2027);
    expect((float) $christmas->net)->toBe(539.47);
    expect($christmas->date->toDateString())->toBe('2027-08-31');
    expect(bonusRow($recurrence, 'easter', 2028))->toBeNull();
});

test('starting after a pay day pays that bonus with the first salary', function () {
    $recurrence = payroll([['2027-09-01', null, 1000]]);

    RecurrenceGenerator::sync($recurrence);

    expect(bonusRow($recurrence, 'easter', 2027))->toBeNull();
    $leave = bonusRow($recurrence, 'leave', 2027);
    expect((float) $leave->net)->toBe(167.12); // 122/365 of 500
    expect($leave->date->toDateString())->toBe('2027-09-30');
    expect((float) bonusRow($recurrence, 'christmas', 2027)->net)->toBe(535.09); // 122/237.5
    expect((float) bonusRow($recurrence, 'easter', 2028)->net)->toBe(500.0);
});

test('contractors and non-payroll recurrences get no bonuses', function () {
    $contractor = payroll([['2027-01-01', null, 1000]], 'contractor');
    RecurrenceGenerator::sync($contractor);

    expect(Transaction::where('recurrence_id', $contractor->id)->where('managed_key', 'like', '%bonus%')->count())->toBe(0);
});

test('a reconciled bonus is frozen and re-syncs leave it alone', function () {
    $recurrence = payroll([['2027-01-01', null, 1000]]);
    RecurrenceGenerator::sync($recurrence);
    bonusRow($recurrence, 'easter', 2027)->update(['is_reconciled' => true, 'net' => 510]);

    $recurrence->entries()->update(['net' => 2000]);
    RecurrenceGenerator::sync($recurrence->fresh());

    expect((float) bonusRow($recurrence, 'easter', 2027)->net)->toBe(510.0);
    expect((float) bonusRow($recurrence, 'leave', 2027)->net)->toBe(1000.0);
});

test('a holiday added on the pay day moves the same row', function () {
    $recurrence = payroll([['2027-01-01', null, 1000]]);
    RecurrenceGenerator::sync($recurrence);
    $id = bonusRow($recurrence, 'christmas', 2027)->id;

    PublicHoliday::factory()->create(['date' => '2027-12-20']);
    RecurrenceGenerator::sync($recurrence->fresh());

    $row = bonusRow($recurrence, 'christmas', 2027);
    expect($row->id)->toBe($id);
    expect($row->date->toDateString())->toBe('2027-12-17');
});

test('no bonus is generated before the managed floor or past the horizon', function () {
    Setting::current()->update(['managed_from' => '2026-09-01']);
    $recurrence = payroll([['2026-01-01', null, 1000]]);

    RecurrenceGenerator::sync($recurrence);

    expect(bonusRow($recurrence, 'easter', 2026))->toBeNull();
    expect(bonusRow($recurrence, 'leave', 2026))->toBeNull();
    expect(bonusRow($recurrence, 'christmas', 2026))->not->toBeNull();
    // Horizon 2028-10-23: June 2028 is in, Christmas 2028 is not.
    expect(bonusRow($recurrence, 'leave', 2028))->not->toBeNull();
    expect(bonusRow($recurrence, 'christmas', 2028))->toBeNull();
});

test('bonus FMY and EFKA reach the tax ledgers', function () {
    $recurrence = payroll([['2027-01-01', null, 1000, 80, 150, 250]]);

    RecurrenceGenerator::sync($recurrence);

    // December 2027: the salary plus the Christmas bonus.
    expect(collect(FmyLedger::monthly())->firstWhere('month', '2027-12')['amount'])->toBe(163.33);
    expect(collect(EfkaLedger::monthly())->firstWhere('month', '2027-12')['amount'])->toBe(816.67);
});
