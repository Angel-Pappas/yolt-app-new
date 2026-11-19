<?php

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An agreed piece of work for a customer. A container only: the money is in its
 * installments, which are ordinary income transactions (`transactions.project_id`).
 * `is_excluded` switches every unreconciled installment off at once.
 *
 * @property int $id
 * @property int|null $user_id
 * @property int $entity_id
 * @property string $name
 * @property string|null $agreed_total
 * @property string|null $notes
 * @property bool $is_excluded
 */
#[Fillable(['entity_id', 'name', 'agreed_total', 'notes', 'is_excluded'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'agreed_total' => 'decimal:2',
            'is_excluded' => 'boolean',
        ];
    }

    /** @return BelongsTo<Entity, $this> */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    /** @return HasMany<Transaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
