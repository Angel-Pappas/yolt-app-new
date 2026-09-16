<?php

namespace App\Http\Controllers;

use App\Support\EfkaLedger;
use App\Support\FmyLedger;
use App\Support\IncomeTaxLedger;
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
        $currentYear = Carbon::now()->format('Y');

        return Inertia::render('taxes/index', [
            'vat' => [
                'payable_this_month' => self::sumDue(VatLedger::obligations(), $currentMonth),
                'net' => self::monthAmount(VatLedger::monthly(), $currentMonth, 'net'),
            ],
            'withheld' => [
                'payable_this_month' => self::sumDue(WithheldLedger::obligations(), $currentMonth),
                'this_month' => self::monthAmount(WithheldLedger::monthly(), $currentMonth),
            ],
            'fmy' => [
                'payable_this_month' => self::sumDue(FmyLedger::obligations(), $currentMonth),
                'this_month' => self::monthAmount(FmyLedger::monthly(), $currentMonth),
            ],
            'efka' => [
                'payable_this_month' => self::sumDue(EfkaLedger::obligations(), $currentMonth),
                'this_month' => self::monthAmount(EfkaLedger::monthly(), $currentMonth),
            ],
            'income' => [
                'payable_this_month' => self::sumDue(IncomeTaxLedger::obligations(), $currentMonth),
                'this_year' => self::sumDue(IncomeTaxLedger::obligations(), $currentYear),
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

    public function fmy(): Response
    {
        return Inertia::render('taxes/fmy', [
            'rows' => FmyLedger::monthly(),
        ]);
    }

    public function efka(): Response
    {
        return Inertia::render('taxes/efka', [
            'rows' => EfkaLedger::monthly(),
        ]);
    }

    /**
     * Total amount of the given obligations whose due date starts with the prefix —
     * a "YYYY-MM" month or a "YYYY" year.
     *
     * @param  list<TaxObligation>  $obligations
     */
    private static function sumDue(array $obligations, string $prefix): float
    {
        $total = 0.0;
        foreach ($obligations as $obligation) {
            if (str_starts_with($obligation->dueDate, $prefix)) {
                $total += $obligation->amount;
            }
        }

        return round($total, 2);
    }

    /**
     * A field off the ledger row for the given month (0 when the month has no row).
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private static function monthAmount(array $rows, string $month, string $key = 'amount'): float
    {
        foreach ($rows as $row) {
            if (($row['month'] ?? null) === $month) {
                return (float) ($row[$key] ?? 0);
            }
        }

        return 0.0;
    }
}
