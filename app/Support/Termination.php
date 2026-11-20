<?php

namespace App\Support;

use App\Models\Entity;
use App\Models\Recurrence;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * What it would cost to dismiss an employee on a given date — an estimate for
 * planning, in gross amounts, with each part listed:
 *
 *  1. **Severance** (αποζημίωση απόλυσης, salaried employees — Law 2112/1920 as
 *     amended by 4093/2012): by completed years with the company —
 *     under 1: none · 1–<4: 2 months · 4–<6: 3 · 6–<8: 4 · 8–<10: 5 · 10: 6 · 11: 7 ·
 *     12: 8 · 13: 9 · 14: 10 · 15: 11 · 16 or more: 12. A month is the regular
 *     monthly pay — counted only up to 8 × the minimum daily wage × 30 — plus 1/6
 *     for the bonuses and leave allowance. **With notice** (1 month for 1–<2 years,
 *     2 for 2–<5, 3 for 5–<10, 4 from 10) half of it is owed. (The extra for staff
 *     with 17+ years on 12/11/2012 is not modelled.)
 *  2. **Unused leave**: this year's remaining leave days × the daily pay
 *     (monthly ÷ 25).
 *  3. **Leave allowance** for the year so far, if not yet paid (it is paid on
 *     20 July): ½ month × the share of the year worked.
 *  4. **Christmas / Easter bonus** earned in the running accrual period (1 May–31
 *     Dec: a month × 25/24 per 237,5 days; 1 Jan–30 Apr: ½ month per 120 days), if
 *     not yet paid.
 *
 * Severance up to €60.000 is tax-free and carries no social insurance; the other
 * parts are ordinary pay. Sources (checked 2026-11-12):
 * https://paymaster.gr/apozimiosi-apolysis/ , https://www.misthoi.gr/tools/severance ,
 * https://paymaster.gr/apozimiosi-adeias-mi-liftheisas/ ; the bonus and allowance
 * rules are the ones {@see PayrollBonuses} already generates by.
 */
final class Termination
{
    /** Completed years of service → months of pay owed without notice. */
    public static function severanceMonths(int $years): int
    {
        return match (true) {
            $years < 1 => 0,
            $years < 4 => 2,
            $years < 6 => 3,
            $years < 8 => 4,
            $years < 10 => 5,
            $years >= 16 => 12,
            default => $years - 4, // 10 → 6 … 15 → 11
        };
    }

    /** Completed years of service → months of notice the law asks for. */
    public static function noticeMonths(int $years): int
    {
        return match (true) {
            $years < 1 => 0,
            $years < 2 => 1,
            $years < 5 => 2,
            $years < 10 => 3,
            default => 4,
        };
    }

    /**
     * @return array{date: string, hire_date: string|null, years: int, gross: float, notice_months: int, lines: list<array{label: string, detail: string, amount: float}>, total: float}
     */
    public static function estimate(Entity $employee, CarbonInterface|string $date, bool $withNotice = false): array
    {
        $on = CarbonImmutable::parse(is_string($date) ? $date : $date->format('Y-m-d'));
        $profile = $employee->employeeProfile;
        $hire = $profile?->hire_date !== null ? CarbonImmutable::parse($profile->hire_date->format('Y-m-d')) : null;
        $years = $hire !== null && $hire->lessThanOrEqualTo($on) ? (int) floor($hire->diffInYears($on)) : 0;
        $gross = self::monthlyGross($employee, $on);
        $rules = TaxRules::at($on);
        $lines = [];

        // 1. Severance.
        $months = self::severanceMonths($years);
        $cap = 8 * 30 * $rules->number('severance.min_daily_wage');
        $counted = $cap > 0 ? min($gross, $cap) : $gross;
        $severance = round($months * $counted * 7 / 6 * ($withNotice ? 0.5 : 1), 2);
        $lines[] = [
            'label' => 'Severance',
            'detail' => $months === 0
                ? 'None under one completed year of service'
                : sprintf('%d completed year%s → %d month%s of pay + 1/6%s', $years, $years === 1 ? '' : 's', $months, $months === 1 ? '' : 's', $withNotice ? ', halved for notice given' : ''),
            'amount' => $severance,
        ];

        // 2. Unused leave of the year.
        $leave = Leave::summary($employee, $on->year);
        $unused = max(0.0, $leave['remaining']);
        $lines[] = [
            'label' => 'Unused leave',
            'detail' => sprintf('%s day%s left of %d × daily pay (monthly ÷ 25)', self::trim($unused), $unused === 1.0 ? '' : 's', $leave['entitled']),
            'amount' => round($unused * $gross / 25, 2),
        ];

        $yearStart = CarbonImmutable::create($on->year, 1, 1);
        $employedFrom = $hire !== null && $hire->greaterThan($yearStart) ? $hire : $yearStart;
        $holidays = WorkingDays::holidaySet();

        // 3. Leave allowance, if this year's hasn't been paid yet.
        $allowancePaid = PayrollBonuses::dueDate(PayrollBonuses::LEAVE, $on->year, $holidays);
        if ($on->lessThan($allowancePaid) && $employedFrom->lessThanOrEqualTo($on)) {
            $days = (int) $employedFrom->diffInDays($on) + 1;
            $lines[] = [
                'label' => 'Leave allowance',
                'detail' => sprintf('½ month × %d of %d days of the year', $days, $on->daysInYear),
                'amount' => round($gross / 2 * $days / $on->daysInYear, 2),
            ];
        }

        // 4. The bonus being earned on that date, if not yet paid.
        $kind = $on->month <= 4 ? PayrollBonuses::EASTER : PayrollBonuses::CHRISTMAS;
        [$periodStart] = PayrollBonuses::accrualPeriod($kind, $on->year);
        $from = $hire !== null && $hire->greaterThan($periodStart) ? $hire : $periodStart;
        if ($on->lessThan(PayrollBonuses::dueDate($kind, $on->year, $holidays)) && $from->lessThanOrEqualTo($on)) {
            $days = (int) $from->diffInDays($on) + 1;
            [$share, $fullDays] = $kind === PayrollBonuses::EASTER ? [0.5, 120.0] : [25 / 24, 237.5];
            $lines[] = [
                'label' => PayrollBonuses::LABELS[$kind],
                'detail' => sprintf('%d day%s earned since %s', $days, $days === 1 ? '' : 's', $from->format('d/m/Y')),
                'amount' => round($gross * $share * min($days, $fullDays) / $fullDays, 2),
            ];
        }

        return [
            'date' => $on->format('Y-m-d'),
            'hire_date' => $hire?->format('Y-m-d'),
            'years' => $years,
            'gross' => $gross,
            'notice_months' => self::noticeMonths($years),
            'lines' => $lines,
            'total' => round(array_sum(array_column($lines, 'amount')), 2),
        ];
    }

    /**
     * The employee's regular monthly gross on a date: the salary in force on their
     * payroll recurrences (the latest period when the date is past the last one).
     */
    public static function monthlyGross(Entity $employee, CarbonInterface $date): float
    {
        return round((float) $employee->recurrences()
            ->where('is_payroll', true)
            ->where('interval_unit', 'month')
            ->with('entries')
            ->get()
            ->sum(function (Recurrence $r) use ($date): float {
                $entry = RecurrenceGenerator::entryFor($r, $date);

                return (float) ($entry->net ?? 0);
            }), 2);
    }

    private static function trim(float $n): string
    {
        return rtrim(rtrim(number_format($n, 1, ',', ''), '0'), ',');
    }
}
