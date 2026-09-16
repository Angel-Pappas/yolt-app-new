<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Support\EfkaLedger;
use App\Support\FmyLedger;
use App\Support\IncomeTaxLedger;
use App\Support\TaxObligation;
use App\Support\VatLedger;
use App\Support\WithheldLedger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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

        // Compute each tax's obligations once, reused for the payment schedule (the
        // full flat list) and each card's "payable this month" figure.
        $vat = VatLedger::obligations();
        $withheld = WithheldLedger::obligations();
        $fmy = FmyLedger::obligations();
        $efka = EfkaLedger::obligations();
        $income = IncomeTaxLedger::obligations();

        $obligations = collect(array_merge($vat, $withheld, $fmy, $efka, $income))
            ->map(fn (TaxObligation $o): array => $o->toArray())
            ->sortBy('due_date')
            ->values()
            ->all();

        return Inertia::render('taxes/index', [
            'obligations' => $obligations,
            'current_month' => $currentMonth,
            'vat' => [
                'payable_this_month' => self::sumDue($vat, $currentMonth),
                'net' => self::monthAmount(VatLedger::monthly(), $currentMonth, 'net'),
            ],
            'withheld' => [
                'payable_this_month' => self::sumDue($withheld, $currentMonth),
                'this_month' => self::monthAmount(WithheldLedger::monthly(), $currentMonth),
            ],
            'fmy' => [
                'payable_this_month' => self::sumDue($fmy, $currentMonth),
                'this_month' => self::monthAmount(FmyLedger::monthly(), $currentMonth),
            ],
            'efka' => [
                'payable_this_month' => self::sumDue($efka, $currentMonth),
                'this_month' => self::monthAmount(EfkaLedger::monthly(), $currentMonth),
            ],
            'income' => [
                'payable_this_month' => self::sumDue($income, $currentMonth),
                'this_year' => self::sumDue($income, $currentYear),
            ],
        ]);
    }

    public function vat(): Response
    {
        return Inertia::render('taxes/vat', [
            'rows' => VatLedger::monthly(),
            'transactions' => self::contributing(fn (Builder $q) => $q
                ->whereIn('type', ['income', 'expense'])
                ->where('vat_amount', '>', 0)),
        ]);
    }

    public function withheld(): Response
    {
        return Inertia::render('taxes/withheld', [
            'rows' => WithheldLedger::monthly(),
            'transactions' => self::contributing(fn (Builder $q) => $q
                ->where('type', 'expense')
                ->where('withheld_amount', '>', 0)),
        ]);
    }

    public function fmy(): Response
    {
        return Inertia::render('taxes/fmy', [
            'rows' => FmyLedger::monthly(),
            'transactions' => self::contributing(fn (Builder $q) => $q
                ->where('fmy_amount', '>', 0)),
        ]);
    }

    public function efka(): Response
    {
        return Inertia::render('taxes/efka', [
            'rows' => EfkaLedger::monthly(),
            'transactions' => self::contributing(fn (Builder $q) => $q
                ->where(fn (Builder $inner) => $inner
                    ->where('efka_employee_amount', '>', 0)
                    ->orWhere('efka_employer_amount', '>', 0))),
        ]);
    }

    /**
     * The transactions contributing to a tax, scoped by the given query callback,
     * with the wallet + entity loaded for the read-only table.
     *
     * @param  \Closure(Builder<Transaction>): mixed  $scope
     * @return Collection<int, Transaction>
     */
    private static function contributing(\Closure $scope): Collection
    {
        $query = Transaction::query()
            ->with(['wallet:id,name', 'entity:id,name'])
            ->orderBy('date')
            ->orderBy('id');

        $scope($query);

        return $query->get();
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
