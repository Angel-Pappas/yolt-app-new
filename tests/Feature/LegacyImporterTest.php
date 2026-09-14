<?php

use App\Models\Entity;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Support\Legacy\LegacyImporter;

/**
 * A representative slice of the old Supabase finance dataset, keyed by table, using
 * UUID ids and Postgres-shaped values (string numerics, ISO timestamps, boolean
 * flags).
 *
 * @return array<string, array<int, array<string, mixed>>>
 */
function legacyFixture(): array
{
    $ts = '2026-01-15T10:00:00+00:00';

    return [
        'profiles' => [
            ['id' => 'u1', 'email' => 'owner@example.com', 'full_name' => 'Owner'],
        ],
        'entities' => [
            ['id' => 'e1', 'user_id' => 'u1', 'name' => 'Acme', 'vat_number' => 'GR123', 'created_at' => $ts, 'is_deleted' => false, 'deleted_at' => null],
        ],
        'wallets' => [
            ['id' => 'w1', 'user_id' => 'u1', 'name' => 'Alpha Bank', 'starting_balance' => '100.00', 'created_at' => $ts, 'is_deleted' => false, 'deleted_at' => null],
            ['id' => 'w2', 'user_id' => 'u1', 'name' => 'Cash', 'starting_balance' => '0.00', 'created_at' => $ts, 'is_deleted' => false, 'deleted_at' => null],
        ],
        'vat_rates' => [
            ['id' => 'v1', 'user_id' => 'u1', 'name' => '24%', 'rate' => '24.00', 'created_at' => $ts, 'is_deleted' => false, 'deleted_at' => null],
        ],
        'withheld_tax_rates' => [
            ['id' => 'wt1', 'user_id' => 'u1', 'name' => '20%', 'rate' => '20.00', 'created_at' => $ts, 'is_deleted' => false, 'deleted_at' => null],
        ],
        'categories' => [
            ['id' => 'c1', 'user_id' => 'u1', 'name' => 'Fuel', 'type' => 'expense', 'created_at' => $ts, 'is_deleted' => false, 'deleted_at' => null],
        ],
        'transactions' => [
            [
                'id' => 't1', 'user_id' => 'u1', 'date' => '2026-03-01', 'invoice_date' => '2026-03-01',
                'description' => 'Fuel buy', 'type' => 'expense', 'net' => '100.00', 'vat_amount' => '24.00',
                'withheld_amount' => '0', 'entity_id' => 'e1', 'category_id' => 'c1', 'wallet_id' => 'w1',
                'to_wallet_id' => null, 'vat_rate_id' => 'v1', 'is_reconciled' => true, 'invoice_month' => 3,
                'invoice_not_required' => false, 'created_at' => $ts, 'is_deleted' => false, 'deleted_at' => null,
            ],
            [
                'id' => 't2', 'user_id' => 'u1', 'date' => '2026-03-05', 'invoice_date' => '2026-03-05',
                'description' => 'Move cash', 'type' => 'transfer', 'net' => '50.00', 'vat_amount' => '0',
                'withheld_amount' => '0', 'entity_id' => null, 'category_id' => null, 'wallet_id' => 'w1',
                'to_wallet_id' => 'w2', 'vat_rate_id' => null, 'is_reconciled' => false, 'invoice_month' => null,
                'invoice_not_required' => false, 'created_at' => $ts, 'is_deleted' => false, 'deleted_at' => null,
            ],
            [
                'id' => 't3', 'user_id' => 'u1', 'date' => '2026-03-09', 'invoice_date' => '2026-03-09',
                'description' => 'Deleted one', 'type' => 'income', 'net' => '10.00', 'vat_amount' => '0',
                'withheld_amount' => '0', 'entity_id' => null, 'category_id' => null, 'wallet_id' => 'w1',
                'to_wallet_id' => null, 'vat_rate_id' => null, 'is_reconciled' => false, 'invoice_month' => null,
                'invoice_not_required' => false, 'created_at' => $ts, 'is_deleted' => true, 'deleted_at' => $ts,
            ],
        ],
        'transaction_vat_lines' => [
            ['id' => 'vl1', 'user_id' => 'u1', 'transaction_id' => 't1', 'net' => '100.00', 'vat_rate_id' => 'v1', 'vat_amount' => '24.00', 'position' => 0, 'created_at' => $ts],
        ],
        'transaction_withheld_lines' => [],
    ];
}

function runImporter(bool $pretend = false): array
{
    $fixture = legacyFixture();
    $importer = new LegacyImporter(fn (string $t) => $fixture[$t] ?? []);

    return $importer->run($pretend);
}

test('pretend mode reports counts and writes nothing', function () {
    User::factory()->admin()->create(['email' => 'owner@example.com']);

    $counts = runImporter(pretend: true);

    expect($counts['transactions'])->toBe(3);
    expect($counts['entities'])->toBe(1);
    expect(Transaction::count())->toBe(0);
    expect(Entity::count())->toBe(0);
});

test('the full dataset migrates with counts', function () {
    User::factory()->admin()->create(['email' => 'owner@example.com']);

    $counts = runImporter();

    expect($counts)->toMatchArray([
        'entities' => 1,
        'wallets' => 2,
        'vat_rates' => 1,
        'categories' => 1,
        'transactions' => 3,
        'transaction_vat_lines' => 1,
    ]);
});

test('foreign keys are remapped to the new ids', function () {
    User::factory()->admin()->create(['email' => 'owner@example.com']);
    runImporter();

    $alpha = Wallet::where('name', 'Alpha Bank')->first();
    $acme = Entity::where('name', 'Acme')->first();
    $t1 = Transaction::where('description', 'Fuel buy')->first();

    expect($t1->wallet_id)->toBe($alpha->id);
    expect($t1->entity_id)->toBe($acme->id);
    expect($t1->net)->toBe('100.00');
    expect($t1->is_reconciled)->toBeTrue();
    expect($t1->invoice_month)->toBe(3);
    expect($t1->vatLines)->toHaveCount(1);
    expect($t1->vatLines->first()->net)->toBe('100.00');
});

test('a transfer keeps both wallet sides', function () {
    User::factory()->admin()->create(['email' => 'owner@example.com']);
    runImporter();

    $alpha = Wallet::where('name', 'Alpha Bank')->first();
    $cash = Wallet::where('name', 'Cash')->first();
    $transfer = Transaction::where('type', 'transfer')->first();

    expect($transfer->wallet_id)->toBe($alpha->id);
    expect($transfer->to_wallet_id)->toBe($cash->id);
});

test('soft-deleted rows stay soft-deleted', function () {
    User::factory()->admin()->create(['email' => 'owner@example.com']);
    runImporter();

    // 2 active transactions, the third is soft-deleted.
    expect(Transaction::count())->toBe(2);
    expect(Transaction::withTrashed()->count())->toBe(3);
    expect(Transaction::withTrashed()->where('description', 'Deleted one')->first()->deleted_at)
        ->not->toBeNull();
});

test('created-by is mapped to the matching user by email', function () {
    $user = User::factory()->admin()->create(['email' => 'owner@example.com']);
    runImporter();

    expect(Transaction::where('description', 'Fuel buy')->first()->user_id)->toBe($user->id);
    expect(Entity::first()->user_id)->toBe($user->id);
});

test('the import replaces any existing finance data', function () {
    User::factory()->admin()->create(['email' => 'owner@example.com']);
    Entity::factory()->create(['name' => 'Stale entity']);
    Wallet::factory()->create(['name' => 'Stale wallet']);

    runImporter();

    expect(Entity::where('name', 'Stale entity')->exists())->toBeFalse();
    expect(Wallet::where('name', 'Stale wallet')->exists())->toBeFalse();
    expect(Entity::count())->toBe(1);
});

test('created-by is null when no matching user exists', function () {
    // No users at all.
    runImporter();

    expect(Transaction::where('description', 'Fuel buy')->first()->user_id)->toBeNull();
});
