<?php

namespace App\Support;

use App\Models\RatePeriod;
use App\Models\VatRate;
use App\Models\WithheldTaxRate;
use Carbon\CarbonInterface;

/**
 * A VAT or withholding rate's percentage **on a date**: its latest dated change
 * starting on or before the date, else the rate's own base percentage. So a change
 * in the law is entered once ("from 1/7 the rate is 25 %") and applies only to what
 * is invoiced from that day on. Loaded once per request.
 */
class Rates
{
    /** @var array<string, array<int, array{base: float, changes: list<array{from: string, rate: float}>}>>|null */
    private ?array $book = null;

    public static function vat(?int $id, CarbonInterface|string|null $date = null): float
    {
        return app(self::class)->rate('vat', $id, $date);
    }

    public static function withheld(?int $id, CarbonInterface|string|null $date = null): float
    {
        return app(self::class)->rate('withheld', $id, $date);
    }

    /** Forget the loaded rates (after a rate or a dated change is saved or deleted). */
    public static function flush(): void
    {
        app(self::class)->book = null;
    }

    /**
     * A rate's dated changes, oldest first — sent to the forms so the preview picks
     * the same percentage as the server.
     *
     * @return list<array{valid_from: string, rate: string}>
     */
    public static function changes(string $type, int $id): array
    {
        return array_map(
            fn (array $c): array => ['valid_from' => $c['from'], 'rate' => number_format($c['rate'], 2, '.', '')],
            app(self::class)->load()[$type][$id]['changes'] ?? [],
        );
    }

    /** A soft-deleted or unknown rate counts as 0 %. */
    private function rate(string $type, ?int $id, CarbonInterface|string|null $date): float
    {
        if ($id === null) {
            return 0.0;
        }
        $entry = $this->load()[$type][$id] ?? null;
        if ($entry === null) {
            return 0.0;
        }

        $day = $date === null ? now()->format('Y-m-d') : (is_string($date) ? substr($date, 0, 10) : $date->format('Y-m-d'));
        $rate = $entry['base'];
        foreach ($entry['changes'] as $change) {
            if ($change['from'] <= $day) {
                $rate = $change['rate'];
            }
        }

        return $rate;
    }

    /** @return array<string, array<int, array{base: float, changes: list<array{from: string, rate: float}>}>> */
    private function load(): array
    {
        if ($this->book !== null) {
            return $this->book;
        }

        $book = ['vat' => [], 'withheld' => []];
        foreach (VatRate::query()->get(['id', 'rate']) as $r) {
            $book['vat'][$r->id] = ['base' => (float) $r->rate, 'changes' => []];
        }
        foreach (WithheldTaxRate::query()->get(['id', 'rate']) as $r) {
            $book['withheld'][$r->id] = ['base' => (float) $r->rate, 'changes' => []];
        }
        foreach (RatePeriod::query()->orderBy('valid_from')->get() as $p) {
            if (isset($book[$p->rate_type][$p->rate_id])) {
                $book[$p->rate_type][$p->rate_id]['changes'][] = [
                    'from' => $p->valid_from->format('Y-m-d'),
                    'rate' => (float) $p->rate,
                ];
            }
        }

        return $this->book = $book;
    }
}
