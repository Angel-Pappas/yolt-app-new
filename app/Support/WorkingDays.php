<?php

namespace App\Support;

use App\Models\PublicHoliday;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Working-day arithmetic for payment dates. Working days are Mon–Fri excluding the
 * stored public holidays. Monthly taxes are paid on the **last working day of the
 * following month**; recurring transactions move off weekends and holidays too
 * (see {@see RecurrenceGenerator}).
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
     * The date itself when it is a working day, else the first working day after it.
     *
     * @param  array<string, true>|null  $holidays  as for {@see lastWorkingDay()}
     */
    public static function nextWorkingDay(CarbonInterface $date, ?array $holidays = null): Carbon
    {
        $holidays ??= self::holidaySet();

        $day = Carbon::parse($date->format('Y-m-d'));

        while ($day->isWeekend() || isset($holidays[$day->format('Y-m-d')])) {
            $day->addDay();
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
