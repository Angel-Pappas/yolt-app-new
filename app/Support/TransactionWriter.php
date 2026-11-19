<?php

namespace App\Support;

use App\Models\Project;
use App\Models\Transaction;

/**
 * Writing a transaction's amounts: the one place that turns validated form data into
 * a saved transaction with its VAT and withholding lines, and that rescales a saved
 * one to a new amount. Shared by the transaction form, reconciling and the bank
 * import, so a row looks the same however it was made.
 */
final class TransactionWriter
{
    /**
     * Set a transaction's fields from validated input and (re)write its VAT and
     * withholding lines wholesale. Shared by store and update; handles both a
     * transfer and an income/expense, nulling the other shape's fields so a type
     * change on edit leaves no stale data behind.
     *
     * @param  array<string, mixed>  $data
     */
    public static function persist(Transaction $transaction, array $data): void
    {
        if (array_key_exists('is_one_off', $data)) {
            $transaction->is_one_off = (bool) $data['is_one_off'];
        }
        self::applyIncomeTags($transaction, $data);

        if ($data['type'] === 'transfer') {
            $transaction->fill([
                'type' => 'transfer',
                'date' => $data['date'],
                'invoice_date' => $data['invoice_date'],
                'description' => $data['description'] ?? '',
                'wallet_id' => $data['wallet_id'],
                'to_wallet_id' => $data['to_wallet_id'],
                'entity_id' => null,
                'category_id' => null,
                'vat_rate_id' => null,
                'net' => round((float) $data['net'], 2),
                'vat_amount' => 0,
                'withheld_amount' => 0,
                'fmy_amount' => null,
                'efka_employee_amount' => null,
                'efka_employer_amount' => null,
            ]);
            $transaction->save();
            $transaction->vatLines()->delete();
            $transaction->withheldLines()->delete();

            return;
        }

        if (isset($data['category_id']) && AmountLines::isPayrollCategory((int) $data['category_id'])) {
            // Payroll: store the net (gross) plus the manual FMY/EFKA amounts. VAT and
            // withholding are always zero here; the cash the wallet moves is the "To
            // Pay" = net − FMY − employee EFKA, derived by WalletBalances::cashTotal.
            $transaction->fill([
                'type' => $data['type'],
                'date' => $data['date'],
                'invoice_date' => $data['invoice_date'],
                'description' => $data['description'] ?? '',
                'entity_id' => $data['entity_id'] ?? null,
                'category_id' => $data['category_id'],
                'wallet_id' => $data['wallet_id'],
                'to_wallet_id' => null,
                'vat_rate_id' => null,
                'net' => round((float) $data['net'], 2),
                'vat_amount' => 0,
                'withheld_amount' => 0,
                'fmy_amount' => self::payrollAmount($data, 'fmy_amount'),
                'efka_employee_amount' => self::payrollAmount($data, 'efka_employee_amount'),
                'efka_employer_amount' => self::payrollAmount($data, 'efka_employer_amount'),
            ]);
            $transaction->save();
            $transaction->vatLines()->delete();
            $transaction->withheldLines()->delete();

            return;
        }

        $resolved = AmountLines::resolve($data['lines'], $data['amount_mode'], $data['invoice_date']);

        $transaction->fill([
            'type' => $data['type'],
            'date' => $data['date'],
            'invoice_date' => $data['invoice_date'],
            'description' => $data['description'] ?? '',
            'entity_id' => $data['entity_id'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'wallet_id' => $data['wallet_id'],
            'to_wallet_id' => null,
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
        $transaction->save();

        $transaction->vatLines()->delete();
        $transaction->vatLines()->createMany($resolved['vat_lines']);
        $transaction->withheldLines()->delete();
        $transaction->withheldLines()->createMany($resolved['withheld_lines']);
    }

    /**
     * A payroll amount from validated data: the rounded number, or null when blank.
     *
     * @param  array<string, mixed>  $data
     */
    private static function payrollAmount(array $data, string $key): ?float
    {
        return isset($data[$key]) ? round((float) $data[$key], 2) : null;
    }

    /**
     * The income-only tags: status (a new income starts as agreed) and project. Any
     * other type carries neither. A new installment of a switched-off project starts
     * switched off too.
     *
     * @param  array<string, mixed>  $data
     */
    private static function applyIncomeTags(Transaction $transaction, array $data): void
    {
        if ($data['type'] !== 'income') {
            $transaction->income_status = null;
            $transaction->project_id = null;

            return;
        }

        $transaction->income_status = $data['income_status'] ?? $transaction->income_status ?? 'agreed';

        if (array_key_exists('project_id', $data)) {
            $transaction->project_id = $data['project_id'] !== null ? (int) $data['project_id'] : null;
        }

        if (! $transaction->exists && $transaction->project_id !== null
            && Project::query()->whereKey($transaction->project_id)->value('is_excluded')) {
            $transaction->is_excluded = true;
        }
    }

    /**
     * Distribute a new net across a transaction's VAT lines in proportion to their
     * current share (the last line absorbs any rounding remainder so the parts sum
     * exactly), re-deriving each line's VAT from its rate. A withheld line stays
     * coupled to its VAT line (matched by position): its base is reset to the line's
     * new net and its withheld amount re-derived from its rate. The summed
     * net/vat_amount/withheld_amount on the transaction are updated to match.
     */
    public static function rescaleLines(Transaction $transaction, float $newNet): void
    {
        $lines = $transaction->vatLines()->orderBy('position')->get();
        if ($lines->isEmpty()) {
            $transaction->net = number_format($newNet, 2, '.', '');

            return;
        }

        $withheldByPosition = $transaction->withheldLines()->get()->keyBy('position');
        $oldNet = (float) $lines->sum(fn ($l) => (float) $l->net);
        // Rates as in force on the invoice date — what tax law goes by.
        $invoiceDate = $transaction->invoice_date;

        $count = $lines->count();
        $remaining = $newNet;
        $sumNet = 0.0;
        $sumVat = 0.0;
        $sumWithheld = 0.0;

        foreach ($lines->values() as $i => $line) {
            if ($i === $count - 1) {
                $lineNet = round($remaining, 2);
            } elseif ($oldNet > 0) {
                $lineNet = round($newNet * (float) $line->net / $oldNet, 2);
            } else {
                $lineNet = $i === 0 ? $newNet : 0.0;
            }
            $remaining -= $lineNet;

            $rate = Rates::vat($line->vat_rate_id, $invoiceDate);
            $lineVat = round($lineNet * $rate / 100, 2);
            $line->update(['net' => $lineNet, 'vat_amount' => $lineVat]);

            $sumNet += $lineNet;
            $sumVat += $lineVat;

            $withheldLine = $withheldByPosition->get($line->position);
            if ($withheldLine !== null) {
                $wRate = Rates::withheld($withheldLine->withheld_rate_id, $invoiceDate);
                $lineWithheld = round($lineNet * $wRate / 100, 2);
                $withheldLine->update(['net' => $lineNet, 'withheld_amount' => $lineWithheld]);
                $sumWithheld += $lineWithheld;
            }
        }

        $transaction->net = number_format($sumNet, 2, '.', '');
        $transaction->vat_amount = number_format($sumVat, 2, '.', '');
        $transaction->withheld_amount = number_format($sumWithheld, 2, '.', '');
    }

    /**
     * Set a saved transaction's amounts so the cash it moves is exactly `$cash` — what
     * a bank statement line says happened. A transfer takes it as its amount; a
     * payroll row keeps its FMY/EFKA and moves its net by the difference; anything
     * else rescales its VAT/withholding lines (VAT re-derived from each rate) and then
     * absorbs the last rounding cent in its VAT, so the total matches to the cent.
     * The caller saves the transaction.
     */
    public static function toCash(Transaction $transaction, float $cash): void
    {
        $cash = round($cash, 2);
        $current = round(WalletBalances::cashTotal($transaction), 2);
        if (abs($current - $cash) < 0.005) {
            return;
        }

        if ($transaction->type === 'transfer') {
            $transaction->net = number_format($cash, 2, '.', '');

            return;
        }

        if ($transaction->category_id !== null && AmountLines::isPayrollCategory($transaction->category_id)) {
            $transaction->net = number_format((float) $transaction->net + $cash - $current, 2, '.', '');

            return;
        }

        // Cash per unit of net (VAT on top, withholding off it); found by trying the
        // amount itself when the row has no amount yet.
        $net = (float) $transaction->net;
        if ($net <= 0 || $current <= 0) {
            self::rescaleLines($transaction, $cash);
            $net = (float) $transaction->net;
            $current = WalletBalances::cashTotal($transaction);
        }
        $perNet = $net > 0 && $current > 0 ? $current / $net : 1.0;
        self::rescaleLines($transaction, round($cash / $perNet, 2));

        $diff = round($cash - WalletBalances::cashTotal($transaction), 2);
        if ($diff === 0.0) {
            return;
        }
        $line = $transaction->vatLines()->where('vat_amount', '>', 0)->orderByDesc('position')->first();
        if ($line !== null) {
            $line->update(['vat_amount' => round((float) $line->vat_amount + $diff, 2)]);
            $transaction->vat_amount = number_format((float) $transaction->vat_amount + $diff, 2, '.', '');
        } else {
            $line = $transaction->vatLines()->orderByDesc('position')->first();
            $line?->update(['net' => round((float) $line->net + $diff, 2)]);
            $transaction->net = number_format((float) $transaction->net + $diff, 2, '.', '');
        }
    }
}
