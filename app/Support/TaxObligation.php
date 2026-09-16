<?php

namespace App\Support;

/**
 * A single tax payment obligation — an amount of one tax, for one bucket month,
 * due on a specific date. The uniform primitive every tax ledger emits: the
 * per-month "what I pay this month" view aggregates these across taxes, and the
 * future auto-transaction feature will materialize each one as a real payment.
 */
final class TaxObligation
{
    public function __construct(
        /** Tax key: 'vat' | 'withheld' | 'fmy' | 'efka' | 'income'. */
        public readonly string $tax,
        /** The bucket month this obligation settles, 'YYYY-MM'. */
        public readonly string $period,
        public readonly float $amount,
        /** The payment date, 'YYYY-MM-DD' (a working day). */
        public readonly string $dueDate,
    ) {}

    /**
     * @return array{tax: string, period: string, amount: float, due_date: string}
     */
    public function toArray(): array
    {
        return [
            'tax' => $this->tax,
            'period' => $this->period,
            'amount' => $this->amount,
            'due_date' => $this->dueDate,
        ];
    }
}
