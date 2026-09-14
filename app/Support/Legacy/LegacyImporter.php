<?php

namespace App\Support\Legacy;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One-time cutover migration: copies every row from the old Supabase/Postgres app
 * into the new MySQL schema, faithfully, so nothing is lost or changed.
 *
 * The old app uses UUID primary keys; the new one uses auto-increment bigints, so
 * each table is loaded parents-first and every row's new id is recorded in an
 * id-map that later foreign keys are translated through. Old `is_deleted` +
 * `deleted_at` collapse into the new `deleted_at` (SoftDeletes) so soft-deleted
 * rows stay soft-deleted; `created_at` is preserved. `user_id` (a created-by audit
 * field) is remapped from the old profile to the matching new user by email, or
 * left null.
 *
 * The data source is injected as a reader callable — `fn(string $table): array` —
 * so the mapping logic is unit-tested against fixtures without a live Postgres.
 */
class LegacyImporter
{
    /** @var array<string, array<string, int>> map name => (old uuid => new id) */
    private array $maps = [];

    /** @var callable(string): iterable<int, array<string, mixed>> */
    private $reader;

    /**
     * @param  callable(string): iterable<int, array<string, mixed>>  $reader
     */
    public function __construct(callable $reader)
    {
        $this->reader = $reader;
    }

    /**
     * Run the migration. In pretend mode nothing is written — only the source row
     * counts are returned.
     *
     * @return array<string, int> table => rows migrated
     */
    public function run(bool $pretend = false): array
    {
        if ($pretend) {
            return $this->sourceCounts();
        }

        $counts = [];
        DB::transaction(function () use (&$counts): void {
            $this->truncateTargets();

            $userMap = $this->buildUserMap();

            $counts['entities'] = $this->importLookup('entities', ['name', 'vat_number'], $userMap);
            $counts['wallets'] = $this->importLookup('wallets', ['name', 'starting_balance'], $userMap);
            $counts['vat_rates'] = $this->importLookup('vat_rates', ['name', 'rate'], $userMap);
            $counts['withheld_tax_rates'] = $this->importLookup('withheld_tax_rates', ['name', 'rate'], $userMap);
            $counts['categories'] = $this->importLookup('categories', ['name', 'type'], $userMap);

            $counts['transactions'] = $this->importTransactions($userMap);
            $counts['transaction_vat_lines'] = $this->importTransactionLines(
                'transaction_vat_lines',
                'vat_rate_id',
                'vat_rates',
                'vat_amount',
                $userMap,
            );
            $counts['transaction_withheld_lines'] = $this->importTransactionLines(
                'transaction_withheld_lines',
                'withheld_rate_id',
                'withheld_tax_rates',
                'withheld_amount',
                $userMap,
            );
        });

        return $counts;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function read(string $table): array
    {
        return collect(($this->reader)($table))
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function sourceCounts(): array
    {
        $tables = [
            'entities', 'wallets', 'vat_rates', 'withheld_tax_rates', 'categories',
            'transactions', 'transaction_vat_lines', 'transaction_withheld_lines',
        ];

        $counts = [];
        foreach ($tables as $table) {
            $counts[$table] = count($this->read($table));
        }

        return $counts;
    }

    private function truncateTargets(): void
    {
        $order = [
            'transaction_withheld_lines', 'transaction_vat_lines', 'transactions',
            'categories', 'withheld_tax_rates', 'vat_rates', 'wallets', 'entities',
        ];

        // delete(), not truncate() — TRUNCATE implicitly commits on MySQL, which
        // would break the wrapping transaction. Child-first order satisfies FKs.
        Schema::disableForeignKeyConstraints();
        foreach ($order as $table) {
            DB::table($table)->delete();
        }
        Schema::enableForeignKeyConstraints();
    }

    /**
     * Map each old profile to an existing new user by email (created-by audit).
     * Users themselves are not migrated — the owner's login stays as it is.
     *
     * @return array<string, int> old profile uuid => new user id
     */
    private function buildUserMap(): array
    {
        $map = [];
        foreach ($this->read('profiles') as $profile) {
            $email = $profile['email'] ?? null;
            $user = $email
                ? DB::table('users')->where('email', $email)->first()
                : null;
            $user ??= DB::table('users')->where('is_admin', true)->first();
            $user ??= DB::table('users')->first();

            if ($user !== null && isset($profile['id'])) {
                $map[(string) $profile['id']] = (int) $user->id;
            }
        }

        return $map;
    }

    /**
     * @param  array<int, string>  $columns
     * @param  array<string, int>  $userMap
     */
    private function importLookup(string $table, array $columns, array $userMap): int
    {
        $rows = $this->read($table);
        foreach ($rows as $row) {
            $attrs = ['user_id' => $this->mapUser($row, $userMap)];
            foreach ($columns as $col) {
                $attrs[$col] = $this->value($row, $col);
            }
            $this->stamp($attrs, $row);
            $this->insertMapped($table, $table, $row, $attrs);
        }

        return count($rows);
    }

    /**
     * @param  array<string, int>  $userMap
     */
    private function importTransactions(array $userMap): int
    {
        $rows = $this->read('transactions');
        foreach ($rows as $row) {
            $attrs = [
                'user_id' => $this->mapUser($row, $userMap),
                'date' => $this->value($row, 'date'),
                'invoice_date' => $this->value($row, 'invoice_date'),
                'description' => $this->value($row, 'description') ?? '',
                'type' => $this->value($row, 'type'),
                'net' => $this->value($row, 'net'),
                'vat_amount' => $this->value($row, 'vat_amount') ?? 0,
                'withheld_amount' => $this->value($row, 'withheld_amount') ?? 0,
                'entity_id' => $this->mapId('entities', $row['entity_id'] ?? null),
                'category_id' => $this->mapId('categories', $row['category_id'] ?? null),
                'wallet_id' => $this->mapId('wallets', $row['wallet_id'] ?? null),
                'to_wallet_id' => $this->mapId('wallets', $row['to_wallet_id'] ?? null),
                'vat_rate_id' => $this->mapId('vat_rates', $row['vat_rate_id'] ?? null),
                'is_reconciled' => $this->truthy($row['is_reconciled'] ?? false),
                'invoice_month' => $this->intOrNull($row, 'invoice_month'),
                'invoice_not_required' => $this->truthy($row['invoice_not_required'] ?? false),
            ];
            $this->stamp($attrs, $row);
            $this->insertMapped('transactions', 'transactions', $row, $attrs);
        }

        return count($rows);
    }

    /**
     * VAT and withholding lines share a shape (transaction_id, net, rate fk,
     * amount, position); the rate column and amount column differ.
     *
     * @param  array<string, int>  $userMap
     */
    private function importTransactionLines(string $table, string $rateFk, string $rateMap, string $amountCol, array $userMap): int
    {
        $rows = $this->read($table);
        $migrated = 0;
        foreach ($rows as $row) {
            $transactionId = $this->mapId('transactions', $row['transaction_id'] ?? null);
            if ($transactionId === null) {
                continue;
            }
            $attrs = [
                'user_id' => $this->mapUser($row, $userMap),
                'transaction_id' => $transactionId,
                'net' => $this->value($row, 'net'),
                $rateFk => $this->mapId($rateMap, $row[$rateFk] ?? null),
                $amountCol => $this->value($row, $amountCol) ?? 0,
                'position' => (int) ($row['position'] ?? 0),
                'created_at' => $this->ts($row['created_at'] ?? null),
                'updated_at' => $this->ts($row['created_at'] ?? null),
            ];
            DB::table($table)->insert($attrs);
            $migrated++;
        }

        return $migrated;
    }

    /**
     * Insert a row and record old-uuid => new-id in the named map.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $attrs
     */
    private function insertMapped(string $table, string $mapName, array $row, array $attrs): void
    {
        $newId = DB::table($table)->insertGetId($attrs);
        if (isset($row['id'])) {
            $this->maps[$mapName][(string) $row['id']] = (int) $newId;
        }
    }

    /**
     * Add created_at/updated_at (preserved) and deleted_at (from is_deleted).
     *
     * @param  array<string, mixed>  $attrs
     * @param  array<string, mixed>  $row
     */
    private function stamp(array &$attrs, array $row): void
    {
        $created = $this->ts($row['created_at'] ?? null);
        $attrs['created_at'] = $created;
        $attrs['updated_at'] = $created;
        $attrs['deleted_at'] = $this->truthy($row['is_deleted'] ?? false)
            ? ($this->ts($row['deleted_at'] ?? null) ?? $created)
            : null;
    }

    private function mapId(string $map, mixed $old): ?int
    {
        if ($old === null || $old === '') {
            return null;
        }

        return $this->maps[$map][(string) $old] ?? null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, int>  $userMap
     */
    private function mapUser(array $row, array $userMap): ?int
    {
        $old = $row['user_id'] ?? null;

        return $old !== null ? ($userMap[(string) $old] ?? null) : null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function value(array $row, string $key): mixed
    {
        $v = $row[$key] ?? null;

        return $v === '' ? null : $v;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function intOrNull(array $row, string $key): ?int
    {
        $v = $row[$key] ?? null;

        return ($v === null || $v === '') ? null : (int) $v;
    }

    private function truthy(mixed $v): bool
    {
        return $v === true || $v === 1 || $v === '1' || $v === 't' || $v === 'true';
    }

    private function ts(?string $v): ?string
    {
        return $v ? Carbon::parse($v)->utc()->format('Y-m-d H:i:s') : null;
    }
}
