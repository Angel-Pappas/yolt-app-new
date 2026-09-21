<?php

namespace App\Console\Commands;

use App\Support\RecurrenceGenerator;
use App\Support\TaxSync;
use Illuminate\Console\Command;

/**
 * Keep the managed (auto-generated) transactions in sync and advance the rolling
 * 24-month horizon. Runs daily; also invoked after a recurrence changes. Tax
 * synchronisation is added here in Phase C.
 */
class SyncManagedTransactions extends Command
{
    protected $signature = 'managed:sync';

    protected $description = 'Regenerate recurring transactions across the rolling horizon.';

    public function handle(): int
    {
        // Recurrences first, so the tax obligations they feed are computed on the
        // updated set of future income/expenses.
        RecurrenceGenerator::syncAll();
        TaxSync::run();

        $this->info('Managed transactions synced.');

        return self::SUCCESS;
    }
}
