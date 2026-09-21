<?php

namespace App\Models;

use App\Support\RecurrenceGenerator;
use Database\Factories\RecurrenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A recurring income/expense defined on an entity. The fixed template plus a cadence
 * and window; its dated amount timeline is {@see RecurrenceEntry}. The generator
 * ({@see RecurrenceGenerator}) turns it into real transactions.
 *
 * @property int $id
 * @property int|null $user_id
 * @property int $entity_id
 * @property string $type
 * @property string $description
 * @property int|null $category_id
 * @property int $wallet_id
 * @property int|null $vat_rate_id
 * @property int|null $withheld_rate_id
 * @property bool $is_payroll
 * @property int $interval_count
 * @property string $interval_unit
 * @property int|null $day_of_month
 * @property Carbon $start_date
 * @property Carbon|null $end_date
 * @property bool $active
 */
#[Fillable([
    'entity_id',
    'type',
    'description',
    'category_id',
    'wallet_id',
    'vat_rate_id',
    'withheld_rate_id',
    'is_payroll',
    'interval_count',
    'interval_unit',
    'day_of_month',
    'start_date',
    'end_date',
    'active',
])]
class Recurrence extends Model
{
    /** @use HasFactory<RecurrenceFactory> */
    use HasFactory, SoftDeletes;

    public const UNITS = ['week', 'month', 'year'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_payroll' => 'boolean',
            'active' => 'boolean',
            'interval_count' => 'integer',
            'day_of_month' => 'integer',
        ];
    }

    /** @return BelongsTo<Entity, $this> */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<Wallet, $this> */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /** @return HasMany<RecurrenceEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(RecurrenceEntry::class)->orderBy('start_date');
    }

    /** @return HasMany<Transaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
