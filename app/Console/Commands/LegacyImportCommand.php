<?php

namespace App\Console\Commands;

use App\Support\Legacy\LegacyImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time cutover migration from the old Supabase/Postgres app into the new MySQL
 * database. Reads the `legacy` connection (set LEGACY_DB_* in the environment) and
 * replaces the finance + CRM data with a faithful copy of the old app's.
 *
 *   php artisan legacy:import --pretend   # report source counts, write nothing
 *   php artisan legacy:import             # run it (asks for confirmation)
 */
class LegacyImportCommand extends Command
{
    protected $signature = 'legacy:import {--pretend : Report source row counts without writing} {--force : Skip the confirmation prompt}';

    protected $description = 'Migrate all data from the old Supabase/Postgres database into this app';

    public function handle(): int
    {
        $pretend = (bool) $this->option('pretend');

        try {
            DB::connection('legacy')->getPdo();
        } catch (\Throwable $e) {
            $this->error('Cannot connect to the legacy database. Set LEGACY_DB_* in the environment.');
            $this->line($e->getMessage());

            return self::FAILURE;
        }

        if (! $pretend && ! $this->option('force')) {
            $this->warn('This TRUNCATES the finance and CRM tables and reloads them from the legacy database.');
            if (! $this->confirm('Continue?')) {
                $this->info('Aborted.');

                return self::SUCCESS;
            }
        }

        $importer = new LegacyImporter(
            fn (string $table): array => DB::connection('legacy')->table($table)
                ->get()
                ->map(fn ($row): array => (array) $row)
                ->all(),
        );

        $counts = $importer->run($pretend);

        $this->newLine();
        $this->info($pretend ? 'Legacy source row counts:' : 'Migrated rows:');
        $this->table(
            ['Table', 'Rows'],
            collect($counts)->map(fn ($n, $t) => [$t, $n])->values()->all(),
        );

        return self::SUCCESS;
    }
}
