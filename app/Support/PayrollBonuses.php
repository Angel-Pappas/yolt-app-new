<?php

namespace App\Support;

use App\Models\Recurrence;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The three statutory payroll bonuses an employee's payroll recurrence generates on
 * top of the monthly salary (rules verified 2026-10-23, sources in Summary.md):
 *
 * - **Easter bonus** (δώρο Πάσχα): accrues 1 Jan – 30 Apr; full = ½ monthly salary,
 *   else 1/15 of it per 8 calendar days (full at 120 days). Paid on the Friday before
 *   Holy Monday (Orthodox Easter − 9 days).
 * - **Christmas bonus** (δώρο Χριστουγέννων): accrues 1 May – 31 Dec; full = 1 monthly
 *   salary, else 2/25 of it per 19 calendar days (full at 237.5 days), × 25/24 (the
 *   1,041666 leave-allowance share — it applies because the leave allowance is paid
 *   inside this period, in June). Paid on 20 December.
 * - **Leave allowance** (επίδομα αδείας): ½ monthly salary for the calendar year,
 *   pro rata to the days employed in it (Angel's rule). Paid on the last working day
 *   of June.
 *
 * Amounts follow every period of the recurrence day by day: each employed day in the
 * accrual period contributes the salary in force that day, so a raise part-way
 * through counts for exactly the days it covers. Net, FMY and both EFKA shares scale
 * alike — an estimate until the payslip figure is set on reconciling. A pay day on a
 * weekend or holiday moves to the previous working day; one before the employment
 * starts moves to the first salary, one after it ends to the last salary.
 *
 * @phpstan-type Kind 'easter'|'christmas'|'leave'
 */
final class PayrollBonuses
{
    public const EASTER = 'easter';

    public const CHRISTMAS = 'christmas';

    public const LEAVE = 'leave';

    /** @var array<Kind, string> */
    public const LABELS = [
        self::EASTER => 'Easter bonus',
        self::LEAVE => 'Leave allowance',
        self::CHRISTMAS => 'Christmas bonus',
    ];

    /** Only an employee's monthly payroll recurrence earns bonuses — never contractors. */
    public static function applies(Recurrence $recurrence): bool
    {
        return $recurrence->is_payroll
            && $recurrence->interval_unit === 'month'
            && $recurrence->entity?->type === 'employee';
    }

    /**
     * Every bonus the recurrence earns from its first year through the given horizon,
     * keyed by its managed slot (`recurrence:{id}:bonus-{kind}:{year}`) — stable
     * whatever the pay day, so a moved date updates the same row.
     *
     * @param  array<string, true>  $holidays
     * @return array<string, array{label: string, date: CarbonImmutable, amounts: array{net: float, fmy_amount: float|null, efka_employee_amount: float|null, efka_employer_amount: float|null}}>
     */
    public static function forRecurrence(Recurrence $recurrence, CarbonInterface $from, CarbonInterface $until, array $holidays): array
    {
        if (! self::applies($recurrence)) {
            return [];
        }

        $out = [];
        for ($year = $recurrence->start_date->year; $year <= $until->year; $year++) {
            foreach (array_keys(self::LABELS) as $kind) {
                $amounts = self::amounts($recurrence, $kind, $year);
                if ($amounts === null) {
                    continue;
                }
                $date = self::payDate($recurrence, $kind, $year, $holidays);
                if ($date->lessThan($from->startOfDay()) || $date->greaterThan($until)) {
                    continue;
                }
                $out["recurrence:{$recurrence->id}:bonus-{$kind}:{$year}"] = [
                    'label' => self::LABELS[$kind],
                    'date' => $date,
                    'amounts' => $amounts,
                ];
            }
        }

        return $out;
    }

    /**
     * One bonus's amounts: Σ (field in force each employed day of the accrual
     * period) × full share ÷ max(employed days, days for a full bonus). Below the
     * full-bonus days that is the statutory pro rata; at or above it, the full share
     * of the day-weighted average salary. Null when no day of the period is employed.
     *
     * @param  Kind  $kind
     * @return array{net: float, fmy_amount: float|null, efka_employee_amount: float|null, efka_employer_amount: float|null}|null
     */
    public static function amounts(Recurrence $recurrence, string $kind, int $year): ?array
    {
        [$first, $last] = self::accrualPeriod($kind, $year);
        $fields = ['net', 'fmy_amount', 'efka_employee_amount', 'efka_employer_amount'];
        $sums = array_fill_keys($fields, 0.0);
        $present = array_fill_keys($fields, false);
        $days = 0;

        for ($day = $first; $day->lessThanOrEqualTo($last); $day = $day->addDay()) {
            $entry = RecurrenceGenerator::entryFor($recurrence, $day);
            if ($entry === null) {
                continue; // before the start, after the end, or in a gap
            }
            $days++;
            foreach ($fields as $field) {
                if ($entry->{$field} !== null) {
                    $sums[$field] += (float) $entry->{$field};
                    $present[$field] = true;
                }
            }
        }

        if ($days === 0) {
            return null;
        }

        [$share, $fullDays] = match ($kind) {
            self::EASTER => [0.5, 120.0],
            self::CHRISTMAS => [25 / 24, 237.5],
            self::LEAVE => [0.5, (float) ($first->daysInYear)],
        };
        $divisor = max((float) $days, $fullDays);
        $scale = fn (string $f): float => round($sums[$f] * $share / $divisor, 2);

        return [
            'net' => $scale('net'),
            'fmy_amount' => $present['fmy_amount'] ? $scale('fmy_amount') : null,
            'efka_employee_amount' => $present['efka_employee_amount'] ? $scale('efka_employee_amount') : null,
            'efka_employer_amount' => $present['efka_employer_amount'] ? $scale('efka_employer_amount') : null,
        ];
    }

    /**
     * The first and last day of a bonus's accrual period.
     *
     * @param  Kind  $kind
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function accrualPeriod(string $kind, int $year): array
    {
        return match ($kind) {
            self::EASTER => [CarbonImmutable::create($year, 1, 1), CarbonImmutable::create($year, 4, 30)],
            self::CHRISTMAS => [CarbonImmutable::create($year, 5, 1), CarbonImmutable::create($year, 12, 31)],
            self::LEAVE => [CarbonImmutable::create($year, 1, 1), CarbonImmutable::create($year, 12, 31)],
        };
    }

    /**
     * The bonus's usual pay day (see {@see dueDate()}), moved to the first salary if
     * the employment starts after it, or to the last salary if it ends before it.
     *
     * @param  array<string, true>  $holidays
     * @param  Kind  $kind
     */
    public static function payDate(Recurrence $recurrence, string $kind, int $year, array $holidays): CarbonImmutable
    {
        $date = self::dueDate($kind, $year, $holidays);

        $start = $recurrence->start_date->startOfDay();
        if ($start->greaterThan($date)) {
            $first = self::immutable(WorkingDays::lastWorkingDay($start->year, $start->month, $holidays));

            return $first->greaterThan($date) ? $first : $date;
        }

        $end = $recurrence->end_date?->startOfDay();
        if ($end !== null && $end->lessThan($date)) {
            $last = self::immutable(WorkingDays::lastWorkingDay($end->year, $end->month, $holidays));

            return $last->lessThan($date) ? $last : $date;
        }

        return $date;
    }

    /**
     * The usual pay day: Easter on the Friday before Holy Monday, Christmas on 20
     * December (each moved back to the previous working day if off), leave on the
     * last working day of June.
     *
     * @param  array<string, true>  $holidays
     * @param  Kind  $kind
     */
    public static function dueDate(string $kind, int $year, array $holidays): CarbonImmutable
    {
        return self::immutable(match ($kind) {
            self::EASTER => WorkingDays::previousWorkingDay(self::orthodoxEaster($year)->subDays(9), $holidays),
            self::CHRISTMAS => WorkingDays::previousWorkingDay(CarbonImmutable::create($year, 12, 20), $holidays),
            self::LEAVE => WorkingDays::lastWorkingDay($year, 6, $holidays),
        });
    }

    /**
     * Orthodox Easter Sunday (Gregorian date): Meeus' Julian-calendar algorithm, then
     * + 13 days for the Julian→Gregorian offset, which holds for 1900–2099.
     */
    public static function orthodoxEaster(int $year): CarbonImmutable
    {
        $d = (19 * ($year % 19) + 15) % 30;
        $e = (2 * ($year % 4) + 4 * ($year % 7) - $d + 34) % 7;
        $month = intdiv($d + $e + 114, 31);
        $day = (($d + $e + 114) % 31) + 1;

        return CarbonImmutable::create($year, $month, $day)->addDays(13);
    }

    private static function immutable(CarbonInterface $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date->format('Y-m-d'));
    }
}
