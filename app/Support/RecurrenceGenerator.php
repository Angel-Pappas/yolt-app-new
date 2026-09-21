<?php

namespace App\Support;

use App\Models\Recurrence;
use App\Models\RecurrenceEntry;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\VatRate;
use App\Models\WithheldTaxRate;
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
 * earlier of its `end_date` and a rolling 24-month horizon. For each occurrence the
 * amount in force comes from the matching {@see RecurrenceEntry}; a date no entry
 * covers is skipped. VAT/withholding are computed server-side from the rates' current
 * percentages — never stored on the recurrence — exactly like a hand-entered row.
 */
class RecurrenceGenerator
{
    /** How far past today occurrences are generated. */
    public const HORIZON_MONTHS = 24;

    /** Sync every recurrence — the daily job, and a safety net after any change. */
    public static function syncAll(): void
    {
        Recurrence::query()->withTrashed()->with('entries')->get()
            ->each(fn (Recurrence $r) => self::sync($r));
    }

    /**
     * Make one recurrence's generated transactions match its current definition.
     */
    public static function sync(Recurrence $recurrence): void
    {
        $recurrence->loadMissing('entries');

        $vPct = $recurrence->vat_rate_id
            ? (float) VatRate::query()->whereKey($recurrence->vat_rate_id)->value('rate')
            : 0.0;
        $wPct = $recurrence->withheld_rate_id
            ? (float) WithheldTaxRate::query()->whereKey($recurrence->withheld_rate_id)->value('rate')
            : 0.0;

        /** @var array<string, RecurrenceEntry> $desired */
        $desired = self::desiredOccurrences($recurrence);

        /** @var Collection<string, Transaction> $existing */
        $existing = Transaction::query()
            ->where('recurrence_id', $recurrence->id)
            ->get()
            ->keyBy('managed_key');

        foreach ($desired as $key => $entry) {
            $row = $existing->get($key);
            if ($row !== null) {
                // A reconciled occurrence is frozen; leave it exactly as it was.
                if (! $row->is_reconciled) {
                    self::write($row, $recurrence, $key, $entry, $vPct, $wPct);
                }
                $existing->forget($key);

                continue;
            }
            self::write(new Transaction, $recurrence, $key, $entry, $vPct, $wPct);
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
     * The entry in force for each wanted occurrence, keyed by its managed slot.
     * A trashed/inactive recurrence wants nothing.
     *
     * @return array<string, RecurrenceEntry>
     */
    private static function desiredOccurrences(Recurrence $recurrence): array
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
            $out[$key] = $entry;
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

    /** The entry whose span covers a date (first match; entries are start-ordered). */
    private static function entryFor(Recurrence $recurrence, CarbonInterface $date): ?RecurrenceEntry
    {
        foreach ($recurrence->entries as $entry) {
            $from = $entry->start_date->copy()->startOfDay();
            $to = $entry->end_date?->copy()->endOfDay();
            if ($date->greaterThanOrEqualTo($from) && ($to === null || $date->lessThanOrEqualTo($to))) {
                return $entry;
            }
        }

        return null;
    }

    /** Fill and save one generated transaction (and its VAT/withholding lines). */
    private static function write(
        Transaction $t,
        Recurrence $r,
        string $key,
        RecurrenceEntry $entry,
        float $vPct,
        float $wPct,
    ): void {
        $net = round((float) $entry->net, 2);
        $iso = explode(':', $key)[2];

        $base = [
            'type' => $r->type,
            'date' => $iso,
            'invoice_date' => $iso,
            'description' => $r->description,
            'entity_id' => $r->entity_id,
            'category_id' => $r->category_id,
            'wallet_id' => $r->wallet_id,
            'to_wallet_id' => null,
            'net' => $net,
            'is_reconciled' => false,
            'source' => 'recurrence',
            'recurrence_id' => $r->id,
            'managed_key' => $key,
        ];

        if ($r->is_payroll) {
            $t->fill($base + [
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

        $vat = round($net * $vPct / 100, 2);
        $withheld = round($net * $wPct / 100, 2);

        $t->fill($base + [
            'vat_rate_id' => $r->vat_rate_id,
            'vat_amount' => $vat,
            'withheld_amount' => $withheld,
            'fmy_amount' => null,
            'efka_employee_amount' => null,
            'efka_employer_amount' => null,
        ]);
        $t->user_id = $r->user_id;
        $t->save();

        $t->vatLines()->delete();
        $t->vatLines()->create([
            'net' => $net,
            'vat_rate_id' => $r->vat_rate_id,
            'vat_amount' => $vat,
            'position' => 0,
        ]);
        $t->withheldLines()->delete();
        if ($r->withheld_rate_id !== null) {
            $t->withheldLines()->create([
                'net' => $net,
                'withheld_rate_id' => $r->withheld_rate_id,
                'withheld_amount' => $withheld,
                'position' => 0,
            ]);
        }
    }
}
