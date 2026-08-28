<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Transactions — the core Finance feature. Gated by `can:access-finance`; shared
 * company data. This slice is the read-only list; the entry form (with VAT /
 * withholding lines and the money math) and filtering follow.
 */
class TransactionController extends Controller
{
    public function index(): Response
    {
        $transactions = Transaction::query()
            ->with([
                'wallet:id,name',
                'toWallet:id,name',
                'entity:id,name',
                'category:id,name',
            ])
            ->orderBy('date')
            ->orderBy('id')
            ->get([
                'id',
                'date',
                'invoice_date',
                'description',
                'type',
                'net',
                'vat_amount',
                'withheld_amount',
                'wallet_id',
                'to_wallet_id',
                'entity_id',
                'category_id',
                'is_reconciled',
            ]);

        return Inertia::render('transactions/index', [
            'transactions' => $transactions,
        ]);
    }
}
