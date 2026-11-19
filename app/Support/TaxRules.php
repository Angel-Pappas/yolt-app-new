<?php

namespace App\Support;

use App\Models\TaxRule;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * The tax rules in force on a date: the latest dated set starting on or before it
 * (the earliest set for any date before them all). Loaded once per request.
 */
class TaxRules
{
    /** @var Collection<int, TaxRule>|null */
    private ?Collection $sets = null;

    public static function at(CarbonInterface|string $date): TaxRuleSet
    {
        return app(self::class)->setAt(is_string($date) ? $date : $date->format('Y-m-d'));
    }

    /** Forget the loaded sets (after a set is saved or deleted). */
    public static function flush(): void
    {
        app(self::class)->sets = null;
    }

    private function setAt(string $date): TaxRuleSet
    {
        $this->sets ??= TaxRule::query()->orderBy('valid_from')->get();

        $inForce = $this->sets->first();
        foreach ($this->sets as $set) {
            if ($set->valid_from->format('Y-m-d') <= $date) {
                $inForce = $set;
            }
        }

        // No set stored at all (a database seeded without the migration's rows):
        // fall back to the built-in law rather than calculating with zeros.
        if ($inForce === null) {
            $defaults = TaxRuleDefaults::sets();
            $key = array_key_last($defaults);

            return new TaxRuleSet($key, $defaults[$key]['data']);
        }

        return new TaxRuleSet($inForce->valid_from->format('Y-m-d'), $inForce->data);
    }
}
