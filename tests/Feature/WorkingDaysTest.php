<?php

use App\Models\PublicHoliday;
use App\Support\WorkingDays;

test('last working day of a month ending on a weekday is that day', function () {
    // 2026-08-31 is a Monday, 2026-09-30 a Wednesday, 2026-12-31 a Thursday.
    expect(WorkingDays::lastWorkingDay(2026, 8, [])->format('Y-m-d'))->toBe('2026-08-31');
    expect(WorkingDays::lastWorkingDay(2026, 9, [])->format('Y-m-d'))->toBe('2026-09-30');
    expect(WorkingDays::lastWorkingDay(2026, 12, [])->format('Y-m-d'))->toBe('2026-12-31');
});

test('a month ending on a weekend rolls back to the previous Friday', function () {
    // 2026-10-31 is a Saturday -> Fri 30; 2027-01-31 a Sunday -> Fri 29;
    // 2026-02-28 a Saturday -> Fri 27.
    expect(WorkingDays::lastWorkingDay(2026, 10, [])->format('Y-m-d'))->toBe('2026-10-30');
    expect(WorkingDays::lastWorkingDay(2027, 1, [])->format('Y-m-d'))->toBe('2027-01-29');
    expect(WorkingDays::lastWorkingDay(2026, 2, [])->format('Y-m-d'))->toBe('2026-02-27');
});

test('a public holiday on the last working day rolls back further', function () {
    // Oct 2026 would end on Fri 30; marking it a holiday moves it to Thu 29.
    $holidays = ['2026-10-30' => true];
    expect(WorkingDays::lastWorkingDay(2026, 10, $holidays)->format('Y-m-d'))->toBe('2026-10-29');
});

test('holidays and weekends stack when rolling back', function () {
    // Aug 2026 ends Mon 31; marking Aug 31 a holiday skips it and the 29-30 weekend
    // down to Fri 28.
    $holidays = ['2026-08-31' => true];
    expect(WorkingDays::lastWorkingDay(2026, 8, $holidays)->format('Y-m-d'))->toBe('2026-08-28');
});

test('stored public holidays are used when no set is passed', function () {
    PublicHoliday::factory()->create(['date' => '2026-10-30']);

    expect(WorkingDays::lastWorkingDay(2026, 10)->format('Y-m-d'))->toBe('2026-10-29');
});

test('holidaySet returns every stored date keyed by Y-m-d', function () {
    PublicHoliday::factory()->create(['date' => '2026-03-25']);
    PublicHoliday::factory()->create(['date' => '2026-05-01']);

    $set = WorkingDays::holidaySet();

    expect($set)->toHaveKeys(['2026-03-25', '2026-05-01']);
});

test('next working day of a weekday is that day', function () {
    // 2026-03-24 is a Tuesday.
    expect(WorkingDays::nextWorkingDay(Carbon\Carbon::parse('2026-03-24'), [])->format('Y-m-d'))->toBe('2026-03-24');
});

test('a weekend rolls forward to Monday', function () {
    // 2026-01-10 is a Saturday, 2026-01-11 a Sunday.
    expect(WorkingDays::nextWorkingDay(Carbon\Carbon::parse('2026-01-10'), [])->format('Y-m-d'))->toBe('2026-01-12');
    expect(WorkingDays::nextWorkingDay(Carbon\Carbon::parse('2026-01-11'), [])->format('Y-m-d'))->toBe('2026-01-12');
});

test('a public holiday rolls forward, past a weekend too', function () {
    // Fri 2026-04-10 a holiday -> skips the weekend to Mon 13; Mon 13 also a holiday -> Tue 14.
    expect(WorkingDays::nextWorkingDay(Carbon\Carbon::parse('2026-04-10'), ['2026-04-10' => true])->format('Y-m-d'))->toBe('2026-04-13');
    expect(WorkingDays::nextWorkingDay(Carbon\Carbon::parse('2026-04-10'), ['2026-04-10' => true, '2026-04-13' => true])->format('Y-m-d'))->toBe('2026-04-14');
});
