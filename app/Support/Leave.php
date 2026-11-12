<?php

namespace App\Support;

use App\Models\EmployeeProfile;
use App\Models\Entity;
use App\Models\LeaveEntry;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Annual leave (κανονική άδεια) of an employee, per calendar year.
 *
 * Days the law gives, for a 5-day (6-day) working week:
 *  - the calendar year of hiring: 20 (24) days pro rata to the months worked in it;
 *  - the next calendar year: 21 (25);
 *  - from the third: 22 (26);
 *  - with 10 years at this employer, or 12 years of work in total: 25 (30);
 *  - with 25 years of work in total: 26 (31).
 * Years are those completed by the end of the year in question; work elsewhere comes
 * from the employee's "years worked elsewhere before". An override on the employee
 * replaces all of this. (The law lets the first two years' leave be taken as it
 * accrues, month by month; this shows the whole year's figure.)
 *
 * Sources (checked 2026-11-12): https://taxapps.gr/el/blog/adeies-ergazomenon-2026-neos-ergasiakos-nomos ,
 * https://paymaster.gr/dikaioumenes-imeres-adeias/ , KEPEA-GSEE via
 * https://www.taxheaven.gr/news/68082/kepea-gsee-ethsia-adeia-anapsyxhs-ergazomenwn .
 */
final class Leave
{
    /** The leave days an employee is entitled to in a calendar year. */
    public static function entitlement(?EmployeeProfile $profile, int $year): int
    {
        if ($profile?->leave_days_override !== null) {
            return $profile->leave_days_override;
        }

        $sixDay = $profile?->work_days_per_week === 6;
        $hire = $profile?->hire_date;
        if ($hire === null) {
            return $sixDay ? 24 : 20;
        }
        if ($hire->year > $year) {
            return 0;
        }
        $left = $profile->termination_date;
        if ($left !== null && $left->year < $year) {
            return 0;
        }

        $yearEnd = CarbonImmutable::create($year, 12, 31);
        $here = (int) floor($hire->diffInYears($yearEnd));
        $total = $here + (float) $profile->prior_service_years;

        if ($total >= 25) {
            return $sixDay ? 31 : 26;
        }
        if ($here >= 10 || $total >= 12) {
            return $sixDay ? 30 : 25;
        }
        if ($hire->year === $year) {
            // Pro rata: each month worked (the hiring month counts) earns 1/12.
            $months = 12 - $hire->month + 1;

            return (int) round(($sixDay ? 24 : 20) * $months / 12);
        }
        if ($hire->year === $year - 1) {
            return $sixDay ? 25 : 21;
        }

        return $sixDay ? 26 : 22;
    }

    /**
     * The working days in a date range: every day but Sundays, Saturdays (on a 5-day
     * week) and public holidays.
     *
     * @param  array<string, true>|null  $holidays
     */
    public static function workingDays(CarbonInterface $from, CarbonInterface $to, int $workDaysPerWeek = 5, ?array $holidays = null): int
    {
        $holidays ??= WorkingDays::holidaySet();
        $days = 0;
        for ($day = CarbonImmutable::parse($from->format('Y-m-d')); $day->format('Y-m-d') <= $to->format('Y-m-d'); $day = $day->addDay()) {
            if ($day->isSunday() || ($workDaysPerWeek !== 6 && $day->isSaturday())) {
                continue;
            }
            if (isset($holidays[$day->format('Y-m-d')])) {
                continue;
            }
            $days++;
        }

        return $days;
    }

    /**
     * Entitled, taken and remaining days for a year. Leave is counted in the year it
     * starts.
     *
     * @return array{year: int, entitled: int, taken: float, remaining: float}
     */
    public static function summary(Entity $employee, int $year): array
    {
        $entitled = self::entitlement($employee->employeeProfile, $year);
        $taken = (float) LeaveEntry::query()
            ->where('entity_id', $employee->id)
            ->whereYear('start_date', $year)
            ->sum('days');

        return [
            'year' => $year,
            'entitled' => $entitled,
            'taken' => round($taken, 1),
            'remaining' => round($entitled - $taken, 1),
        ];
    }
}
