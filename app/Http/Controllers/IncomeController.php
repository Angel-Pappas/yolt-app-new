<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Finance ▸ Income: every income transaction with its status tag, so it is easy to
 * see what is due and who has paid. They are ordinary transactions — added, edited,
 * tagged, reconciled and deleted through TransactionController. All of them are sent;
 * the page shows the open ones (agreed or invoiced) unless "Show all".
 */
class IncomeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('income/index', [
            'transactions' => Transaction::query()
                ->withListData()
                ->where('type', 'income')
                ->inBankOrder()
                ->get(),
        ]);
    }
}
