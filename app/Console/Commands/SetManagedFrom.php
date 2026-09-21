<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\Transaction;
use App\Support\RecurrenceGenerator;
use App\Support\TaxSync;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Set (or show) the managed-transaction floor — the date the app starts auto-
 * generating from. Setting it re-runs the full sync, so any already-generated
 * managed rows dated before the floor (recurrence occurrences and tax payments) are
 * purged, except reconciled ones. Usage: `php artisan managed:from 2026-09-01`.
 */
class SetManagedFrom extends Command
{
    protected $signature = 'managed:from {date? : The floor date (YYYY-MM-DD), or omit to show}';

    protected $description = 'Set the managed-transaction floor date and re-sync.';

    public function handle(): int
    {
        $date = $this->argument('date');

        if ($date === null) {
            $current = Setting::current()->managed_from;
            $this->info('Managed floor: '.($current?->toDateString() ?? 'none'));

            return self::SUCCESS;
        }

        $parsed = Carbon::parse($date)->toDateString();
        Setting::current()->update(['managed_from' => $parsed]);

        // The floor means the app owns nothing before it — so drop EVERY managed
        // (recurrence/tax) row dated before the floor, reconciled ones included. The
        // normal reconcile-freeze only protects rows on or after the floor; a
        // reconciled pre-floor row is a leftover (e.g. reconciled during testing, or
        // left behind when the floor moves forward), never real manual history, which
        // is always source-null and untouched here.
        $purged = Transaction::query()
            ->whereIn('source', ['recurrence', 'tax'])
            ->whereDate('date', '<', $parsed)
            ->delete();

        RecurrenceGenerator::syncAll();
        TaxSync::run();

        $this->info("Managed floor set to {$parsed}; purged {$purged} pre-floor managed row(s); re-synced.");

        return self::SUCCESS;
    }
}
