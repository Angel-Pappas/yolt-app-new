<?php

namespace App\Support;

use App\Http\Controllers\TransactionController;
use App\Models\Category;
use App\Models\VatRate;
use App\Models\WithheldTaxRate;

/**
 * The one definition of how an income/expense's amount lines become money — shared
 * by a hand-entered transaction ({@see TransactionController})
 * and every row a recurrence generates ({@see RecurrenceGenerator}), so the two can
 * never drift apart.
 */
class AmountLines
{
    /**
     * The category name that turns an expense into a payroll entry: the form then
     * captures FMY + EFKA (employee/employer) instead of VAT/withholding lines.
     */
    public const PAYROLL_CATEGORY = 'Payroll';

    public static function isPayrollCategory(?int $categoryId): bool
    {
        return $categoryId !== null
            && Category::query()->whereKey($categoryId)->value('name') === self::PAYROLL_CATEGORY;
    }

    /**
     * Resolve each amount line into a VAT line and — when the line carries a
     * withholding rate — a withheld line whose base is that same line's net, all
     * computed server-side from the rates' current percentages (never trusted from
     * the client). The single Net/Total mode governs the whole set of lines:
     *
     *  - "net": the typed amount is the line's net. VAT = net × v, withheld = net × w.
     *  - "total": the typed amount is the cash total (net + VAT − withheld). The net
     *    is reversed out as total ÷ (1 + (v − w)/100); withheld = net × w; and VAT is
     *    anchored to (total − net + withheld) so the line reconstructs to the exact
     *    typed total without double-rounding drift.
     *
     * The withheld line shares its VAT line's `position`, so the two can be
     * re-coupled when the transaction is loaded back into the form.
     *
     * @param  array<int, array{amount: mixed, vat_rate_id?: mixed, withheld_rate_id?: mixed}>  $lines
     * @return array{net: float, vat_amount: float, withheld_amount: float, vat_lines: array<int, array{net: float, vat_rate_id: int|null, vat_amount: float, position: int}>, withheld_lines: array<int, array{net: float, withheld_rate_id: int, withheld_amount: float, position: int}>}
     */
    public static function resolve(array $lines, string $mode): array
    {
        $vatRates = VatRate::query()
            ->whereIn('id', collect($lines)->pluck('vat_rate_id')->filter()->all())
            ->pluck('rate', 'id');
        $withheldRates = WithheldTaxRate::query()
            ->whereIn('id', collect($lines)->pluck('withheld_rate_id')->filter()->all())
            ->pluck('rate', 'id');

        $net = 0.0;
        $vatAmount = 0.0;
        $withheldAmount = 0.0;
        $vatLines = [];
        $withheldLines = [];

        foreach (array_values($lines) as $i => $line) {
            $amount = round((float) $line['amount'], 2);
            $vatRateId = ($line['vat_rate_id'] ?? null) ? (int) $line['vat_rate_id'] : null;
            $withheldRateId = ($line['withheld_rate_id'] ?? null) ? (int) $line['withheld_rate_id'] : null;
            // A rate soft-deleted since the line was saved counts as 0%.
            $v = $vatRateId !== null ? (float) ($vatRates[$vatRateId] ?? 0) : 0.0;
            $w = $withheldRateId !== null ? (float) ($withheldRates[$withheldRateId] ?? 0) : 0.0;

            if ($mode === 'total') {
                $denom = 1 + ($v - $w) / 100;
                $lineNet = $denom > 0 ? round($amount / $denom, 2) : $amount;
                $lineWithheld = round($lineNet * $w / 100, 2);
                $lineVat = round($amount - $lineNet + $lineWithheld, 2);
            } else {
                $lineNet = $amount;
                $lineVat = round($lineNet * $v / 100, 2);
                $lineWithheld = round($lineNet * $w / 100, 2);
            }

            $net += $lineNet;
            $vatAmount += $lineVat;
            $withheldAmount += $lineWithheld;

            $vatLines[] = [
                'net' => $lineNet,
                'vat_rate_id' => $vatRateId,
                'vat_amount' => $lineVat,
                'position' => $i,
            ];

            if ($withheldRateId !== null) {
                $withheldLines[] = [
                    'net' => $lineNet,
                    'withheld_rate_id' => $withheldRateId,
                    'withheld_amount' => $lineWithheld,
                    'position' => $i,
                ];
            }
        }

        return [
            'net' => round($net, 2),
            'vat_amount' => round($vatAmount, 2),
            'withheld_amount' => round($withheldAmount, 2),
            'vat_lines' => $vatLines,
            'withheld_lines' => $withheldLines,
        ];
    }
}
