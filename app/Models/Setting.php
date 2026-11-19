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
 * @property int $agreement_warning_days
 */
#[Fillable(['managed_from', 'tax_wallet_id', 'agreement_warning_days'])]
class Setting extends Model
{
    protected function casts(): array
    {
        return [
            'managed_from' => 'date',
            'agreement_warning_days' => 'integer',
        ];
    }

    /** The one settings row, created on first access. */
    public static function current(): self
    {
        // refresh(): a row just created has only its defaults in the database.
        $setting = static::query()->firstOrCreate([]);

        return $setting->wasRecentlyCreated ? $setting->refresh() : $setting;
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
