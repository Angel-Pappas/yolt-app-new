<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * The single-row app settings. Read the singleton with {@see Setting::current()}.
 *
 * @property int $id
 * @property CarbonImmutable|null $managed_from
 * @property int|null $tax_wallet_id
 */
#[Fillable(['managed_from', 'tax_wallet_id'])]
class Setting extends Model
{
    protected function casts(): array
    {
        return [
            'managed_from' => 'date',
        ];
    }

    /** The one settings row, created on first access. */
    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }

    /**
     * The floor for managed transactions — no recurrence/tax row is generated with a
     * date before it. Null means no floor (generate from each recurrence's own start).
     */
    public static function managedFrom(): ?CarbonInterface
    {
        return static::current()->managed_from?->startOfDay();
    }
}
