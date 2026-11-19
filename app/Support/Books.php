<?php

namespace App\Support;

use App\Models\Transaction;
use Illuminate\Support\Collection;

/**
 * What every forward-looking figure is computed from — the tax ledgers, the income
 * tax estimate, the projection: the real transactions that count, plus what the plan
 * still expects ({@see Plan::counted()}). Built once per request.
 */
final class Books
{
    /** @var Collection<int, Transaction>|null */
    private static ?Collection $real = null;

    public static function flush(): void
    {
        self::$real = null;
    }

    /**
     * The real rows that count (switched-on), every date and state.
     *
     * @return Collection<int, Transaction>
     */
    public static function real(): Collection
    {
        return self::$real ??= Transaction::query()->counted()
            ->with(['entity:id,name,type', 'category:id,name,counts_for_profit'])
            ->get();
    }

    /**
     * Real rows + the plan's open share. Without taxes: what the taxes themselves are
     * computed from (a tax payment never feeds a tax).
     *
     * @return Collection<int, Transaction>
     */
    public static function rows(bool $withTaxes = true): Collection
    {
        return self::real()->concat(Plan::counted($withTaxes));
    }
}
