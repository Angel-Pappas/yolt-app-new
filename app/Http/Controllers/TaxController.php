<?php

namespace App\Http\Controllers;

use App\Support\VatLedger;
use App\Support\WithheldLedger;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Taxes — read-only aggregates over transactions (nothing stored). The index
 * shows a card per tax type with the current period's headline figures; each type
 * has its own page with the full month-by-month ledger, since each computation is
 * genuinely different (VAT rolls credit forward and splits debits into
 * installments; withholding is simply last month's collection).
 */
class TaxController extends Controller
{
    public function index(): Response
    {
        $currentMonth = Carbon::now()->format('Y-m');

        $vat = collect(VatLedger::monthly())->firstWhere('month', $currentMonth);
        $withheld = collect(WithheldLedger::monthly())->firstWhere('month', $currentMonth);

        return Inertia::render('taxes/index', [
            'vat' => [
                'payable_this_month' => $vat['payable_this_month'] ?? 0,
                'net' => $vat['net'] ?? 0,
            ],
            'withheld' => [
                'payable_this_month' => $withheld['payable_this_month'] ?? 0,
                'withheld' => $withheld['withheld'] ?? 0,
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
}
