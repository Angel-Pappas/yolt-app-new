<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Set (or show) the plan's floor — nothing is planned before it (recurrence
 * occurrences, tax payments): the books before it are the hand-kept history.
 * Usage: `php artisan managed:from 2026-09-01`.
 */
class SetManagedFrom extends Command
{
    protected $signature = 'managed:from {date? : The floor date (YYYY-MM-DD), or omit to show}';

    protected $description = 'Set (or show) the date the plan starts from.';

    public function handle(): int
    {
        $date = $this->argument('date');

        if ($date === null) {
            $current = Setting::current()->managed_from;
            $this->info('Plan floor: '.($current?->toDateString() ?? 'none'));

            return self::SUCCESS;
        }

        $parsed = Carbon::parse($date)->toDateString();
        Setting::current()->update(['managed_from' => $parsed]);
        $this->info("Plan floor set to {$parsed}.");

        return self::SUCCESS;
    }
}
