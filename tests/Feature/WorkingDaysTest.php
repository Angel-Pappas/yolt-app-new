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
