<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Finance ▸ One-offs: the transactions tagged `is_one_off` (added from this page), so
 * one-off expenses can be seen apart from the day-to-day list. They are ordinary
 * transactions — added, edited, reconciled and deleted through TransactionController.
 * All of them are sent; the page shows the open (unreconciled) ones unless "Show all".
 */
class OneOffController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('one-offs/index', [
            'transactions' => Transaction::query()
                ->withListData()
                ->where('is_one_off', true)
                ->orderBy('date')
                ->orderBy('id')
                ->get(),
        ]);
    }
}
