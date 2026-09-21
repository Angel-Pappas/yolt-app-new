<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Entity;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Materialises the live tax obligations into real "managed" transactions and keeps
 * them in sync. When a month's tax is > 0 a matching expense exists — category
 * *Taxes*, entity *State*, dated on the obligation's due date, amount = the
 * obligation — so future tax outflows show up in the ledger and cash flow. On every
 * recompute the amount is updated; an obligation that drops to ≤ 0 (or disappears)
 * has its managed row removed; one that returns is recreated. Reconciled rows are
 * frozen (a real payment made), exactly like recurrence rows.
 *
 * No feedback loop: a tax row carries vat/withheld/fmy/efka = 0, and the ledgers only
 * sum those fields, so a generated tax row never re-enters a tax calculation — it just
 * moves the wallet, which is its whole purpose.
 */
class TaxSync
{
    private const LABELS = [
        'vat' => 'VAT',
        'withheld' => 'Withholding tax',
        'fmy' => 'FMY',
        'efka' => 'EFKA',
        'income' => 'Income tax',
    ];

    public static function run(): void
    {
        $wallet = Wallet::query()->orderBy('id')->first();
        if ($wallet === null) {
            return; // no wallet to pay from yet
        }

        $stateId = self::stateEntity()->id;
        $taxesId = self::taxesCategory()->id;
        $floor = Setting::managedFrom();

        /** @var array<string, TaxObligation> $desired */
        $desired = [];
        foreach (self::allObligations() as $obligation) {
            if ($obligation->amount <= 0) {
                continue;
            }
            // No tax payment is generated before the managed floor — the app takes
            // over from manual history there.
            if ($floor !== null && Carbon::parse($obligation->dueDate)->lessThan($floor)) {
                continue;
            }
            $desired["tax:{$obligation->tax}:{$obligation->period}"] = $obligation;
        }

        /** @var Collection<string, Transaction> $existing */
        $existing = Transaction::query()
            ->where('source', 'tax')
            ->get()
            ->keyBy('managed_key');

        foreach ($desired as $key => $obligation) {
            $row = $existing->get($key);
            if ($row !== null) {
                if (! $row->is_reconciled) {
                    self::write($row, $obligation, $key, $wallet->id, $stateId, $taxesId);
                }
                $existing->forget($key);

                continue;
            }
            self::write(new Transaction, $obligation, $key, $wallet->id, $stateId, $taxesId);
        }

        // Obligations no longer owed: drop the unreconciled rows, keep reconciled ones.
        foreach ($existing as $row) {
            if (! $row->is_reconciled) {
                $row->delete();
            }
        }
    }

    /** @return list<TaxObligation> */
    private static function allObligations(): array
    {
        return array_merge(
            VatLedger::obligations(),
            WithheldLedger::obligations(),
            FmyLedger::obligations(),
            EfkaLedger::obligations(),
            IncomeTaxLedger::obligations(),
        );
    }

    private static function write(
        Transaction $t,
        TaxObligation $obligation,
        string $key,
        int $walletId,
        int $stateId,
        int $taxesId,
    ): void {
        $t->fill([
            'type' => 'expense',
            'date' => $obligation->dueDate,
            'invoice_date' => $obligation->dueDate,
            'description' => self::label($obligation),
            'entity_id' => $stateId,
            'category_id' => $taxesId,
            'wallet_id' => $walletId,
            'to_wallet_id' => null,
            'vat_rate_id' => null,
            'net' => round($obligation->amount, 2),
            'vat_amount' => 0,
            'withheld_amount' => 0,
            'fmy_amount' => null,
            'efka_employee_amount' => null,
            'efka_employer_amount' => null,
            'is_reconciled' => false,
            'source' => 'tax',
            'recurrence_id' => null,
            'managed_key' => $key,
        ]);
        $t->save();
        $t->vatLines()->delete();
        $t->withheldLines()->delete();
    }

    private static function label(TaxObligation $obligation): string
    {
        $name = self::LABELS[$obligation->tax] ?? ucfirst($obligation->tax);
        // Append "-01" so a short month can't overflow the parsed date.
        $month = Carbon::createFromFormat('Y-m-d', $obligation->period.'-01')->format('F Y');

        return "{$name} {$month}";
    }

    private static function stateEntity(): Entity
    {
        return Entity::firstOrCreate(['type' => 'state'], ['name' => 'State']);
    }

    private static function taxesCategory(): Category
    {
        return Category::firstOrCreate(['name' => 'Taxes', 'type' => 'expense']);
    }
}
