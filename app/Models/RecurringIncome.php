<?php

namespace App\Models;

use App\Support\RecurringIncomeLines;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A customer's recurring income (rare): the same amount every month on a day, from a
 * date until an optional end. It creates the customer's payment lines
 * ({@see RecurringIncomeLines}).
 *
 * @property int $id
 * @property int|null $user_id
 * @property int $entity_id
 * @property int $wallet_id
 * @property int|null $vat_rate_id
 * @property int|null $category_id
 * @property string $description
 * @property string $net
 * @property int $day_of_month
 * @property Carbon $start_date
 * @property Carbon|null $end_date
 */
#[Fillable(['entity_id', 'wallet_id', 'vat_rate_id', 'category_id', 'description', 'net', 'day_of_month', 'start_date', 'end_date'])]
class RecurringIncome extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'net' => 'decimal:2',
            'day_of_month' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /** @return BelongsTo<Entity, $this> */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    /** @return BelongsTo<VatRate, $this> */
    public function vatRate(): BelongsTo
    {
        return $this->belongsTo(VatRate::class);
    }
}
