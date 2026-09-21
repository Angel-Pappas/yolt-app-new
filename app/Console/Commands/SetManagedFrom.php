<?php

namespace App\Console\Commands;

use App\Models\Setting;
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

        RecurrenceGenerator::syncAll();
        TaxSync::run();

        $this->info("Managed floor set to {$parsed}; managed transactions re-synced.");

        return self::SUCCESS;
    }
}
