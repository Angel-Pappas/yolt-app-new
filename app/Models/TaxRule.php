<?php

namespace App\Models;

use App\Support\TaxRules;
use App\Support\TaxRuleSet;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One dated snapshot of the tax-law figures (see {@see TaxRuleSet} for
 * the shape of `data`). Read the set in force on a date with
 * {@see TaxRules::at()}.
 *
 * @property int $id
 * @property Carbon $valid_from
 * @property array<string, mixed> $data
 * @property string|null $note
 */
#[Fillable(['valid_from', 'data', 'note'])]
class TaxRule extends Model
{
    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'data' => 'array',
        ];
    }

    /** A change to the rules must be seen by every calculation in the same request. */
    protected static function booted(): void
    {
        static::saved(fn () => TaxRules::flush());
        static::deleted(fn () => TaxRules::flush());
    }
}
