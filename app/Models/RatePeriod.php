<?php

namespace App\Models;

use App\Support\Rates;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A dated change of a VAT or withholding rate: from `valid_from` on, the rate is
 * `rate` %. Read a rate on a date with {@see Rates}.
 *
 * @property int $id
 * @property string $rate_type
 * @property int $rate_id
 * @property Carbon $valid_from
 * @property string $rate
 */
#[Fillable(['rate_type', 'rate_id', 'valid_from', 'rate'])]
class RatePeriod extends Model
{
    public const TYPES = ['vat', 'withheld'];

    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'rate' => 'decimal:2',
        ];
    }

    /** A change to a rate must be seen by every calculation in the same request. */
    protected static function booted(): void
    {
        static::saved(fn () => Rates::flush());
        static::deleted(fn () => Rates::flush());
    }
}
