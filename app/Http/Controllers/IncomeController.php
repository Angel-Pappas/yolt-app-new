<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * Planning ▸ Income: every customer payment line — what customers owe and what
 * they paid ({@see PaymentController::rows()}). The page shows the unpaid ones
 * unless "Show all", by date or by customer.
 */
class IncomeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('income/index', [
            'payments' => PaymentController::rows(),
        ]);
    }
}
