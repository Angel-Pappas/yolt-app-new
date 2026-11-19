<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Entity;
use App\Models\PlanAdjustment;
use App\Models\PlannedTransaction;
use App\Models\Recurrence;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * The plan: everything the company still expects, kept **beside** the books, never
 * in them (Angel, 2026-11-19). Four sources, one shape ({@see PlanItem}):
 *
 *  - **recurrence occurrences** — salaries, bonuses, supplier and contractor costs —
 *    computed live ({@see RecurrenceGenerator::items()}), key `recurrence:{id}:…`;
 *  - **tax payments** — computed live from the tax ledgers over the books *and* the
 *    rest of the plan, key `tax:{tax}:{period}`;
 *  - **planned one-offs** and **customer payment lines** — kept as planned rows
 *    ({@see PlannedTransaction}), key `planned:{id}`.
 *
 * A real transaction carrying an item's key (`managed_key`) pays it; the item stays
 * open for what is left. A computed item can be moved or cancelled by hand
 * ({@see PlanAdjustment}). Nothing is planned before the floor (`managed_from`);
 * recurrences reach 24 months ahead.
 *
 * Building it costs (an employee's bonuses are worked out day by day), so it is
 * kept in the cache under one fixed key, stamped with a **version** and the day
 * (the horizon and "overdue" follow today): any save — and any form submission —
 * bumps the version, and the next read rebuilds it. Within a request it is held in
 * memory.
 */
final class Plan
{
    private const TAX_LABELS = [
        'vat' => 'VAT',
        'withheld' => 'Withholding tax',
        'fmy' => 'FMY',
        'efka' => 'EFKA',
        'income' => 'Income tax',
    ];

    /** @var Collection<string, PlanItem>|null */
    private static ?Collection $nonTax = null;

    /** @var Collection<string, PlanItem>|null */
    private static ?Collection $tax = null;

    /** Forget the built plan: after any change (in memory and in the cache). */
    public static function flush(): void
    {
        self::$nonTax = null;
        self::$tax = null;
        Books::flush();
        try {
            // A store that can't increment a missing key starts the count instead.
            if (Cache::increment('plan:version') === false) {
                Cache::forever('plan:version', 1);
            }
        } catch (\Throwable) {
            // No cache yet (e.g. while migrating): the stamp check still applies.
        }
    }

    /**
     * A part of the plan from the cache when it was built from the data as it is now
     * (same version, same day), else built and stored.
     *
     * @param  callable(): Collection<string, PlanItem>  $build
     * @return Collection<string, PlanItem>
     */
    private static function cached(string $part, callable $build): Collection
    {
        $stamp = Cache::get('plan:version', 0).'|'.Carbon::today()->toDateString();
        $stored = Cache::get("plan:{$part}");
        if (is_array($stored) && ($stored['stamp'] ?? null) === $stamp && is_array($stored['items'] ?? null)) {
            /** @var array<string, array{key: string, kind: string, planned: bool, attributes: array<string, mixed>, entity: array<string, mixed>|null, category: array<string, mixed>|null, vat_lines: list<array<string, mixed>>, withheld_lines: list<array<string, mixed>>, total: float, paid: float, cancelled: bool, overdue: bool}> $cached */
            $cached = $stored['items'];

            return collect($cached)->map(fn (array $i): PlanItem => PlanItem::fromCache($i));
        }
        $items = $build();
        Cache::forever("plan:{$part}", ['stamp' => $stamp, 'items' => $items->map(fn (PlanItem $i): array => $i->toCache())->all()]);

        return $items;
    }

    /**
     * Every item, earliest first.
     *
     * @return Collection<string, PlanItem>
     */
    public static function items(): Collection
    {
        return self::nonTaxItems()->merge(self::taxItems())
            ->sortBy(fn (PlanItem $i): string => $i->date()->toDateString().'|'.$i->key);
    }

    public static function find(string $key): ?PlanItem
    {
        return self::items()->get($key);
    }

    /**
     * Open items dated on/before their wallet's concluded date — expected then, but
     * nothing real paid them. Switched-off items are left out.
     *
     * @return Collection<string, PlanItem>
     */
    public static function notMatched(): Collection
    {
        return self::items()->filter(fn (PlanItem $i): bool => $i->overdue && ! $i->isExcluded());
    }

    /**
     * What the plan adds to the books ({@see PlanItem::counted()}).
     *
     * @return Collection<int, Transaction>
     */
    public static function counted(bool $withTaxes = true): Collection
    {
        $items = $withTaxes ? self::items() : self::nonTaxItems();

        return $items->map(fn (PlanItem $i): ?Transaction => $i->counted())->filter()->values();
    }

    /**
     * Recurrence occurrences, planned one-offs and payment lines.
     *
     * @return Collection<string, PlanItem>
     */
    public static function nonTaxItems(): Collection
    {
        return self::$nonTax ??= self::cached('non-tax', self::buildNonTax(...));
    }

    /** @return Collection<string, PlanItem> */
    private static function buildNonTax(): Collection
    {
        $holidays = WorkingDays::holidaySet();
        /** @var array<string, array{0: string, 1: Transaction}> $templates */
        $templates = [];

        Recurrence::query()->with(['entries.lines', 'entity.employeeProfile', 'category'])->get()
            ->each(function (Recurrence $r) use (&$templates, $holidays): void {
                foreach (RecurrenceGenerator::items($r, $holidays) as $key => $t) {
                    $templates[$key] = ['recurrence', $t];
                }
            });

        PlannedTransaction::query()->with(['entity:id,name,type', 'category:id,name,counts_for_profit', 'vatLines', 'withheldLines'])->get()
            ->each(function (PlannedTransaction $t) use (&$templates): void {
                $templates[$t->planKey()] = [$t->type === 'income' ? 'payment' : 'one-off', $t];
            });

        return self::decorate($templates);
    }

    /**
     * The tax payments the books and the rest of the plan lead to: every positive
     * obligation from the floor on, an expense to the State in the tax wallet, dated
     * on its due date.
     *
     * @return Collection<string, PlanItem>
     */
    public static function taxItems(): Collection
    {
        return self::$tax ??= self::cached('tax', self::buildTax(...));
    }

    /** @return Collection<string, PlanItem> */
    private static function buildTax(): Collection
    {
        $setting = Setting::current();
        $walletId = $setting->tax_wallet_id !== null
            && Wallet::query()->whereKey($setting->tax_wallet_id)->exists()
                ? $setting->tax_wallet_id
                : Wallet::query()->orderBy('id')->value('id');
        if ($walletId === null) {
            return collect(); // no wallet to pay from yet
        }

        $floor = $setting->managed_from?->toDateString();
        $state = Entity::firstOrCreate(['type' => 'state'], ['name' => 'State']);
        $taxes = Category::firstOrCreate(['name' => 'Taxes', 'type' => 'expense']);

        $templates = [];
        foreach ([...VatLedger::obligations(), ...WithheldLedger::obligations(), ...FmyLedger::obligations(),
            ...EfkaLedger::obligations(), ...IncomeTaxLedger::obligations()] as $obligation) {
            if ($obligation->amount <= 0 || ($floor !== null && $obligation->dueDate < $floor)) {
                continue;
            }
            $key = "tax:{$obligation->tax}:{$obligation->period}";
            $t = new Transaction;
            $t->forceFill([
                'type' => 'expense',
                'date' => $obligation->dueDate,
                'invoice_date' => $obligation->dueDate,
                'description' => self::taxLabel($obligation),
                'entity_id' => $state->id,
                'category_id' => $taxes->id,
                'wallet_id' => $walletId,
                'net' => round($obligation->amount, 2),
                'vat_amount' => 0,
                'withheld_amount' => 0,
                'is_reconciled' => false,
                'is_excluded' => false,
                'source' => 'tax',
                'managed_key' => $key,
            ]);
            $t->setRelation('entity', $state);
            $t->setRelation('category', $taxes);
            $t->setRelation('vatLines', collect());
            $t->setRelation('withheldLines', collect());
            $templates[$key] = ['tax', $t];
        }

        return self::decorate($templates);
    }

    /**
     * Turn templates into items: what real rows have paid, hand adjustments, and
     * whether each is overdue.
     *
     * @param  array<string, array{0: string, 1: Transaction}>  $templates
     * @return Collection<string, PlanItem>
     */
    private static function decorate(array $templates): Collection
    {
        if ($templates === []) {
            return collect();
        }
        $keys = array_keys($templates);

        $paid = [];
        foreach (array_chunk($keys, 500) as $chunk) {
            Transaction::query()->whereIn('managed_key', $chunk)
                ->get(['managed_key', 'net', 'vat_amount', 'withheld_amount', 'fmy_amount', 'efka_employee_amount'])
                ->each(function (Transaction $t) use (&$paid): void {
                    $paid[$t->managed_key] = ($paid[$t->managed_key] ?? 0.0) + WalletBalances::cashTotal($t);
                });
        }

        $adjustments = PlanAdjustment::query()->whereIn('plan_key', $keys)->get()->keyBy('plan_key');
        $concluded = Wallet::withTrashed()->pluck('concluded_until', 'id')
            ->map(fn ($d) => $d !== null ? Carbon::parse($d)->toDateString() : null);

        $items = [];
        foreach ($templates as $key => [$kind, $t]) {
            $adjustment = $adjustments->get($key);
            $donePaid = round($paid[$key] ?? 0.0, 2);
            if ($adjustment?->date !== null) {
                $t->date = $adjustment->date;
                if ($donePaid <= 0) {
                    $t->invoice_date = $adjustment->date;
                }
            }
            $total = round(WalletBalances::cashTotal($t), 2);
            if ($total <= 0) {
                continue; // nothing to expect
            }
            $cancelled = (bool) $adjustment?->cancelled;
            $until = $concluded->get($t->wallet_id);
            $open = ! $cancelled && $total - $donePaid >= 0.005;
            $items[$key] = new PlanItem(
                key: $key,
                kind: $kind,
                template: $t,
                total: $total,
                paid: $donePaid,
                cancelled: $cancelled,
                overdue: $open && $until !== null && $t->date->toDateString() <= $until,
            );
        }

        return collect($items);
    }

    private static function taxLabel(TaxObligation $obligation): string
    {
        $name = self::TAX_LABELS[$obligation->tax] ?? ucfirst($obligation->tax);
        // Append "-01" so a short month can't overflow the parsed date.
        $month = Carbon::createFromFormat('Y-m-d', $obligation->period.'-01')->format('F Y');

        return "{$name} {$month}";
    }
}
