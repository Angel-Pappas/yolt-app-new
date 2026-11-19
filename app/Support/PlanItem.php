<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Entity;
use App\Models\PlannedTransaction;
use App\Models\Transaction;
use App\Models\TransactionVatLine;
use App\Models\TransactionWithheldLine;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One thing the plan expects: a recurrence occurrence, a tax payment, a planned
 * one-off or a customer payment line ({@see Plan}). `template` is the transaction it
 * would be, in full; `paid` is what real rows carrying its key have covered so far.
 */
final class PlanItem
{
    public const KINDS = ['recurrence', 'tax', 'one-off', 'payment'];

    public function __construct(
        public readonly string $key,
        /** 'recurrence' | 'tax' | 'one-off' | 'payment' */
        public readonly string $kind,
        /** The whole transaction it would be, dated on its expected date. */
        public readonly Transaction $template,
        /** The cash it moves in full (always positive). */
        public readonly float $total,
        /** The cash real rows carrying its key have moved so far. */
        public readonly float $paid,
        /** Cancelled by hand (a computed item that won't happen). */
        public readonly bool $cancelled,
        /** Still open and dated on/before its wallet's concluded date. */
        public readonly bool $overdue,
    ) {}

    /**
     * The item as plain data, for the cache (which keeps no objects).
     *
     * @return array{key: string, kind: string, planned: bool, attributes: array<string, mixed>, entity: array<string, mixed>|null, category: array<string, mixed>|null, vat_lines: list<array<string, mixed>>, withheld_lines: list<array<string, mixed>>, total: float, paid: float, cancelled: bool, overdue: bool}
     */
    public function toCache(): array
    {
        $t = $this->template;
        $attrs = fn (?Model $m): ?array => $m?->getAttributes();

        return [
            'key' => $this->key,
            'kind' => $this->kind,
            'planned' => $t instanceof PlannedTransaction,
            'attributes' => $t->getAttributes(),
            'entity' => $attrs($t->entity),
            'category' => $attrs($t->category),
            'vat_lines' => array_values($t->vatLines->map(fn (Model $l): array => $l->getAttributes())->all()),
            'withheld_lines' => array_values($t->withheldLines->map(fn (Model $l): array => $l->getAttributes())->all()),
            'total' => $this->total,
            'paid' => $this->paid,
            'cancelled' => $this->cancelled,
            'overdue' => $this->overdue,
        ];
    }

    /**
     * An item back from {@see toCache()}.
     *
     * @param  array{key: string, kind: string, planned: bool, attributes: array<string, mixed>, entity: array<string, mixed>|null, category: array<string, mixed>|null, vat_lines: list<array<string, mixed>>, withheld_lines: list<array<string, mixed>>, total: float, paid: float, cancelled: bool, overdue: bool}  $data
     */
    public static function fromCache(array $data): self
    {
        $t = $data['planned'] ? new PlannedTransaction : new Transaction;
        $t->setRawAttributes($data['attributes'], true);
        $t->exists = (bool) $data['planned'];
        $t->setRelation('entity', $data['entity'] !== null ? (new Entity)->setRawAttributes($data['entity'], true) : null);
        $t->setRelation('category', $data['category'] !== null ? (new Category)->setRawAttributes($data['category'], true) : null);
        $t->setRelation('vatLines', collect($data['vat_lines'])->map(fn (array $a) => (new TransactionVatLine)->setRawAttributes($a, true)));
        $t->setRelation('withheldLines', collect($data['withheld_lines'])->map(fn (array $a) => (new TransactionWithheldLine)->setRawAttributes($a, true)));

        return new self($data['key'], $data['kind'], $t, (float) $data['total'], (float) $data['paid'], (bool) $data['cancelled'], (bool) $data['overdue']);
    }

    /** The cash still expected. */
    public function open(): float
    {
        return $this->cancelled ? 0.0 : max(round($this->total - $this->paid, 2), 0.0);
    }

    public function isDone(): bool
    {
        return $this->open() < 0.005;
    }

    public function isExcluded(): bool
    {
        return (bool) $this->template->is_excluded;
    }

    /** The day it is expected (a hand-moved date included). */
    public function date(): CarbonInterface
    {
        return $this->template->date;
    }

    /** The planned row behind a one-off or payment line (null for computed items). */
    public function planned(): ?PlannedTransaction
    {
        return $this->template instanceof PlannedTransaction ? $this->template : null;
    }

    /**
     * What it adds to the books: the open share of its amounts, dated on its expected
     * day — or, when overdue (its day is in a concluded period, and nothing paid it),
     * today: it is still expected, just not then. Its invoice date moves along unless
     * the invoice was issued or part of it is already paid. Null when it adds nothing
     * (done, cancelled, switched off).
     */
    public function counted(): ?Transaction
    {
        if ($this->isDone() || $this->isExcluded()) {
            return null;
        }

        $t = $this->template->replicate();
        $share = $this->total > 0 ? $this->open() / $this->total : 1.0;
        if ($share < 0.9999) {
            foreach (['net', 'vat_amount', 'withheld_amount', 'fmy_amount', 'efka_employee_amount', 'efka_employer_amount'] as $field) {
                if ($t->{$field} !== null) {
                    $t->{$field} = number_format((float) $t->{$field} * $share, 2, '.', '');
                }
            }
        }
        if ($this->overdue) {
            $today = Carbon::today();
            $t->date = $today;
            if (! $this->invoiceFixed()) {
                $t->invoice_date = $today;
            }
        }
        $t->managed_key = $this->key;

        return $t;
    }

    /** Whether its invoice date stays put when its payment date moves. */
    public function invoiceFixed(): bool
    {
        return (bool) $this->template->is_issued || (bool) $this->template->invoice_pinned || $this->paid > 0;
    }

    /**
     * The item as the plan's lists show it.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $t = $this->template;

        return [
            'key' => $this->key,
            'kind' => $this->kind,
            'id' => $this->planned()?->id,
            'type' => $t->type,
            'date' => $t->date->toDateString(),
            'invoice_date' => $t->invoice_date->toDateString(),
            'description' => $t->description,
            'entity_id' => $t->entity_id,
            'entity' => $t->entity?->name,
            'category_id' => $t->category_id,
            'category' => $t->category?->name,
            'wallet_id' => $t->wallet_id,
            'recurrence_id' => $t->recurrence_id,
            'net' => (float) $t->net,
            'vat' => (float) $t->vat_amount,
            'total' => round($this->total, 2),
            'paid' => round($this->paid, 2),
            'open' => $this->open(),
            'overdue' => $this->overdue,
            'cancelled' => $this->cancelled,
            'excluded' => $this->isExcluded(),
            'is_issued' => (bool) $t->is_issued,
        ];
    }
}
