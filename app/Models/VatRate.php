<?php

namespace App\Models;

use App\Support\Rates;
use Database\Factories\VatRateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $name
 * @property string $rate
 */
#[Fillable(['name', 'rate'])]
class VatRate extends Model
{
    /** @use HasFactory<VatRateFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
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
