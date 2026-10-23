<?php

namespace App\Support;

use App\Models\Recurrence;
use App\Models\RecurrenceEntry;
use App\Models\RecurrenceEntryLine;
use App\Models\Setting;
use App\Models\Transaction;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Turns a recurrence into real transactions and keeps them in sync. Every managed
 * row a recurrence owns is (re)built to match the current definition: occurrences
 * missing are created, drifted ones updated, unwanted ones soft-deleted — but a
 * reconciled row is frozen and never touched (a change to it is made by reconciling,
 * or by editing the recurrence, per the agreed model).
 *
 * Occurrences run from the recurrence's `start_date` (history included) through the
 * earlier of its `end_date` and a rolling 24-month horizon. Each occurrence is a
 * **slot** (its scheduled day — the row's `managed_key`) paid on a **payment day**:
 * the last working day of the slot's month for contractors and employees, else the
 * slot itself or the next working day after a weekend/holiday
 * ({@see Recurrence::paysOnLastWorkingDay()}). Keying by slot means a moved payment
 * day updates the same row rather than creating another. For each occurrence the
 * amount in force comes from the matching {@see RecurrenceEntry}; a date no entry
 * covers is skipped. Each period's amount lines (VAT and optional withholding per
 * line, Net/Total mode per period) are resolved server-side from the rates' current
 * percentages by the same {@see AmountLines} resolver as a hand-entered row.
 */
class RecurrenceGenerator
{
    /** How far past today occurrences are generated. */
    public const HORIZON_MONTHS = 24;

    /** Sync every recurrence — the daily job, and a safety net after any change. */
    public static function syncAll(): void
    {
        $holidays = WorkingDays::holidaySet();

        Recurrence::query()->withTrashed()->with(['entries.lines', 'entity'])->get()
            ->each(fn (Recurrence $r) => self::sync($r, $holidays));
    }

    /**
     * Make one recurrence's generated transactions match its current definition.
     */
    /**
     * @param  array<string, true>|null  $holidays  the public-holiday set (loaded when null)
     */
    public static function sync(Recurrence $recurrence, ?array $holidays = null): void
    {
        $recurrence->loadMissing(['entries.lines', 'entity']);
        $holidays ??= WorkingDays::holidaySet();

        // Each period's lines resolve to the same money on every occurrence, so
        // resolve once per period rather than once per generated row.
        $resolved = [];
        foreach ($recurrence->entries as $entry) {
            $resolved[$entry->id] = self::resolveEntry($entry);
        }

        $desired = self::desiredOccurrences($recurrence, $holidays);

        /** @var Collection<string, Transaction> $existing */
        $existing = Transaction::query()
            ->where('recurrence_id', $recurrence->id)
            ->get()
            ->keyBy('managed_key');

        foreach ($desired as $key => [$entry, $payDate]) {
            $row = $existing->get($key);
            if ($row !== null) {
                // A reconciled occurrence is frozen; leave it exactly as it was.
                if (! $row->is_reconciled) {
                    self::write($row, $recurrence, $key, $payDate, $entry, $resolved[$entry->id]);
                }
                $existing->forget($key);

                continue;
            }
            self::write(new Transaction, $recurrence, $key, $payDate, $entry, $resolved[$entry->id]);
        }

        // Occurrences no longer wanted: drop the unreconciled ones, keep reconciled
        // history frozen.
        foreach ($existing as $row) {
            if (! $row->is_reconciled) {
                $row->delete();
            }
        }
    }

    /** Remove a recurrence's unreconciled generated rows (used before a hard purge). */
    public static function removeGenerated(Recurrence $recurrence): void
    {
        Transaction::query()
            ->where('recurrence_id', $recurrence->id)
            ->where('is_reconciled', false)
            ->delete();
    }

    /**
     * The entry in force and the payment day for each wanted occurrence, keyed by its
     * managed slot. A trashed/inactive recurrence wants nothing.
     *
     * @param  array<string, true>  $holidays
     * @return array<string, array{0: RecurrenceEntry, 1: CarbonInterface}>
     */
    private static function desiredOccurrences(Recurrence $recurrence, array $holidays): array
    {
        if ($recurrence->trashed() || ! $recurrence->active) {
            return [];
        }

        $out = [];
        foreach (self::occurrenceDates($recurrence) as $date) {
            $entry = self::entryFor($recurrence, $date);
            if ($entry === null) {
                continue;
            }
            $key = "recurrence:{$recurrence->id}:{$date->toDateString()}";
            $out[$key] = [$entry, self::paymentDate($recurrence, $date, $holidays)];
        }

        return $out;
    }

    /**
     * The occurrence dates from max(`start_date`, the managed floor) through
     * min(end_date, today + horizon). The cadence rhythm stays anchored to the real
     * `start_date` (so phase is preserved), but nothing before the floor is emitted —
     * that's the boundary where the app takes over from manual history. Month/year
     * cadence lands on `day_of_month` (clamped to short months); week cadence steps
     * from the start date. Dates are CarbonImmutable, so every step reassigns.
     *
     * @return list<CarbonInterface>
     */
    public static function occurrenceDates(Recurrence $recurrence): array
    {
        $start = $recurrence->start_date->startOfDay();
        $floor = Setting::managedFrom();
        $lower = ($floor !== null && $floor->greaterThan($start)) ? $floor : $start;

        $horizonEnd = Carbon::today()->addMonths(self::HORIZON_MONTHS);
        $end = $recurrence->end_date
            ? $recurrence->end_date->startOfDay()
            : $horizonEnd;
        if ($end->greaterThan($horizonEnd)) {
            $end = $horizonEnd;
        }
        if ($end->lessThan($lower)) {
            return [];
        }

        $step = max(1, $recurrence->interval_count);

        if ($recurrence->interval_unit === 'week') {
            $dates = [];
            for ($cur = $start; $cur->lessThanOrEqualTo($end); $cur = $cur->addWeeks($step)) {
                if ($cur->greaterThanOrEqualTo($lower)) {
                    $dates[] = $cur;
                }
            }

            return $dates;
        }

        $stepMonths = $step * ($recurrence->interval_unit === 'year' ? 12 : 1);
        $day = $recurrence->day_of_month ?? $recurrence->start_date->day;
        // Recompute the day from each month start so a clamp (Feb 28) never drifts
        // the sequence (…31 → 28 → 31, not …28 → 28).
        $onDay = fn (CarbonInterface $m): CarbonInterface => $m->day(min($day, $m->daysInMonth));

        $base = $recurrence->start_date->startOfMonth();

        $dates = [];
        while (true) {
            $occ = $onDay($base);
            if ($occ->greaterThan($end)) {
                break;
            }
            if ($occ->greaterThanOrEqualTo($lower)) {
                $dates[] = $occ;
            }
            $base = $base->addMonths($stepMonths);
        }

        return $dates;
    }

    /**
     * The day a slot is actually paid. Contractors and employees on a monthly or
     * yearly cadence are paid on the last working day of the slot's month; anything
     * else (and any weekly cadence) on the slot, or the next working day after it.
     *
     * @param  array<string, true>|null  $holidays
     */
    public static function paymentDate(Recurrence $recurrence, CarbonInterface $slot, ?array $holidays = null): CarbonInterface
    {
        if ($recurrence->paysOnLastWorkingDay() && $recurrence->interval_unit !== 'week') {
            return WorkingDays::lastWorkingDay($slot->year, $slot->month, $holidays);
        }

        return WorkingDays::nextWorkingDay($slot, $holidays);
    }

    /**
     * The entry in force on a date: the one with the latest `start_date` on or before
     * it, so a later dated change supersedes an earlier one **even when the earlier
     * has no end date** (the next change's start implicitly ends it). A date before
     * the first entry — or inside an explicit end-to-next-start gap — is uncovered
     * (null), and that occurrence is skipped. Entries are start-ordered ascending.
     */
    public static function entryFor(Recurrence $recurrence, CarbonInterface $date): ?RecurrenceEntry
    {
        $inForce = null;
        foreach ($recurrence->entries as $entry) {
            if ($entry->start_date->startOfDay()->greaterThan($date)) {
                break; // this and every later entry start after the date
            }
            $end = $entry->end_date?->endOfDay();
            $inForce = ($end === null || $date->lessThanOrEqualTo($end)) ? $entry : null;
        }

        return $inForce;
    }

    /**
     * What one period resolves to — through {@see AmountLines::resolve()}, the exact
     * resolver a hand-entered transaction uses, so a generated row always matches
     * what the same lines would produce if typed in by hand. Null for a payroll
     * period (its Net/FMY/EFKA are taken straight from the entry).
     *
     * @return array{net: float, vat_amount: float, withheld_amount: float, vat_lines: array<int, array{net: float, vat_rate_id: int|null, vat_amount: float, position: int}>, withheld_lines: array<int, array{net: float, withheld_rate_id: int, withheld_amount: float, position: int}>}|null
     */
    private static function resolveEntry(RecurrenceEntry $entry): ?array
    {
        if ($entry->lines->isEmpty()) {
            return null;
        }

        return AmountLines::resolve(
            $entry->lines->map(fn (RecurrenceEntryLine $l) => [
                'amount' => $l->amount,
                'vat_rate_id' => $l->vat_rate_id,
                'withheld_rate_id' => $l->withheld_rate_id,
            ])->all(),
            $entry->amount_mode,
        );
    }

    /**
     * Fill and save one generated transaction (and its VAT/withholding lines), dated
     * on its payment day.
     *
     * @param  array{net: float, vat_amount: float, withheld_amount: float, vat_lines: array<int, array{net: float, vat_rate_id: int|null, vat_amount: float, position: int}>, withheld_lines: array<int, array{net: float, withheld_rate_id: int, withheld_amount: float, position: int}>}|null  $resolved
     */
    private static function write(
        Transaction $t,
        Recurrence $r,
        string $key,
        CarbonInterface $payDate,
        RecurrenceEntry $entry,
        ?array $resolved,
    ): void {
        $iso = $payDate->toDateString();

        $base = [
            'type' => $r->type,
            'date' => $iso,
            'invoice_date' => $iso,
            'description' => $r->description,
            'entity_id' => $r->entity_id,
            'category_id' => $r->category_id,
            'wallet_id' => $r->wallet_id,
            'to_wallet_id' => null,
            'is_reconciled' => false,
            'source' => 'recurrence',
            'recurrence_id' => $r->id,
            'managed_key' => $key,
        ];

        if ($r->is_payroll || $resolved === null) {
            $t->fill($base + [
                'net' => round((float) $entry->net, 2),
                'vat_rate_id' => null,
                'vat_amount' => 0,
                'withheld_amount' => 0,
                'fmy_amount' => $entry->fmy_amount !== null ? round((float) $entry->fmy_amount, 2) : null,
                'efka_employee_amount' => $entry->efka_employee_amount !== null ? round((float) $entry->efka_employee_amount, 2) : null,
                'efka_employer_amount' => $entry->efka_employer_amount !== null ? round((float) $entry->efka_employer_amount, 2) : null,
            ]);
            $t->user_id = $r->user_id;
            $t->save();
            $t->vatLines()->delete();
            $t->withheldLines()->delete();

            return;
        }

        $t->fill($base + [
            'net' => $resolved['net'],
            'vat_amount' => $resolved['vat_amount'],
            'withheld_amount' => $resolved['withheld_amount'],
            'fmy_amount' => null,
            'efka_employee_amount' => null,
            'efka_employer_amount' => null,
            // A single line keeps the (denormalized) rate; mixed rates = null.
            'vat_rate_id' => count($resolved['vat_lines']) === 1
                ? $resolved['vat_lines'][0]['vat_rate_id']
                : null,
        ]);
        $t->user_id = $r->user_id;
        $t->save();

        $t->vatLines()->delete();
        $t->vatLines()->createMany($resolved['vat_lines']);
        $t->withheldLines()->delete();
        $t->withheldLines()->createMany($resolved['withheld_lines']);
    }
}
