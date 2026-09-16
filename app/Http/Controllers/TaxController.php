<?php

namespace App\Http\Controllers;

use App\Support\TaxObligation;
use App\Support\VatLedger;
use App\Support\WithheldLedger;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Taxes — read-only aggregates over transactions (nothing stored). The index shows a
 * card per tax type with the current period's headline figures; each type has its own
 * page with the full month-by-month ledger. Every monthly tax attributes by
 * `invoice_date` and is paid on the last working day of the following month.
 */
class TaxController extends Controller
{
    public function index(): Response
    {
        $currentMonth = Carbon::now()->format('Y-m');

        $vatRow = collect(VatLedger::monthly())->firstWhere('month', $currentMonth);
        $withheldRow = collect(WithheldLedger::monthly())->firstWhere('month', $currentMonth);

        return Inertia::render('taxes/index', [
            'vat' => [
                'payable_this_month' => self::dueInMonth(VatLedger::obligations(), $currentMonth),
                'net' => $vatRow['net'] ?? 0,
            ],
            'withheld' => [
                'payable_this_month' => self::dueInMonth(WithheldLedger::obligations(), $currentMonth),
                'withheld' => $withheldRow['withheld'] ?? 0,
            ],
        ]);
    }

    public function vat(): Response
    {
        return Inertia::render('taxes/vat', [
            'rows' => VatLedger::monthly(),
        ]);
    }

    public function withheld(): Response
    {
        return Inertia::render('taxes/withheld', [
            'rows' => WithheldLedger::monthly(),
        ]);
    }

    /**
     * Total amount of the given obligations whose due date falls in the "YYYY-MM" month.
     *
     * @param  list<TaxObligation>  $obligations
     */
    private static function dueInMonth(array $obligations, string $month): float
    {
        $total = 0.0;
        foreach ($obligations as $obligation) {
            if (str_starts_with($obligation->dueDate, $month)) {
                $total += $obligation->amount;
            }
        }

        return round($total, 2);
    }
}
