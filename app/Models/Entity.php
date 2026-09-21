<?php

namespace App\Models;

use Database\Factories\EntityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $name
 * @property string|null $type
 * @property string|null $vat_number
 */
#[Fillable(['name', 'type', 'vat_number'])]
class Entity extends Model
{
    /** @use HasFactory<EntityFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The entity types. A null `type` is the unclassified "Cheese" bucket.
     * `shareholder` is both-sided (dividends out / investment in), so it appears in
     * both the income and expense transaction pickers.
     */
    public const TYPES = ['customer', 'supplier', 'contractor', 'employee', 'state', 'shareholder'];

    /** @return HasMany<Transaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /** @return HasMany<Recurrence, $this> */
    public function recurrences(): HasMany
    {
        return $this->hasMany(Recurrence::class);
    }
}
