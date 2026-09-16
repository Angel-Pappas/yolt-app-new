<?php

namespace App\Support;

use App\Models\PublicHoliday;
use Illuminate\Support\Carbon;

/**
 * Working-day arithmetic for tax payment dates. Every monthly tax's bucket is paid
 * on the **last working day of the following month**, where working days are Mon–Fri
 * excluding stored public holidays.
 */
class WorkingDays
{
    /**
     * The last working day (Mon–Fri, not a public holiday) of the given month.
     * Walks back from the last calendar day past weekends and holidays.
     *
     * @param  array<string, true>|null  $holidays  set of 'Y-m-d' => true; loaded
     *                                              from the DB when null. Pass it in
     *                                              (from {@see holidaySet()}) to avoid
     *                                              a query per call in a loop.
     */
    public static function lastWorkingDay(int $year, int $month, ?array $holidays = null): Carbon
    {
        $holidays ??= self::holidaySet();

        $day = Carbon::create($year, $month, 1)->endOfMonth()->startOfDay();

        while ($day->isWeekend() || isset($holidays[$day->format('Y-m-d')])) {
            $day->subDay();
        }

        return $day;
    }

    /**
     * All stored public-holiday dates as a lookup set keyed by 'Y-m-d'. Fetch once
     * and pass to {@see lastWorkingDay()} when computing many months in a ledger.
     *
     * @return array<string, true>
     */
    public static function holidaySet(): array
    {
        $dates = PublicHoliday::query()
            ->pluck('date')
            ->map(fn ($d): string => Carbon::parse($d)->format('Y-m-d'))
            ->all();

        return array_fill_keys($dates, true);
    }
}
